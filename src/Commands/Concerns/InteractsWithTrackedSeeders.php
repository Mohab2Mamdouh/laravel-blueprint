<?php

namespace BlueprintPhp\Laravel\Commands\Concerns;

use BlueprintPhp\Laravel\Models\Seeder;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Shared discovery/execution helpers for the tracked-seeder commands
 * (seed, seed:rollback, seed:reset, seed:status).
 *
 * A "tracked" seeder is a timestamp-prefixed PHP file (e.g.
 * 1786300000_FooSeeder.php or 2026-08-13_FooSeeder.php) that `return`s a Seeder
 * instance exposing up()/down() (run() is still accepted as a legacy alias for
 * up()). Tracking is per-database in the `seeders` table.
 *
 * Target selection (portable across a single-DB app and a multi-tenant one):
 *   default        → config('blueprint.seeders.default_target') (SEEDER_TARGET env, 'central' by default).
 *   --central      → central seeders (database/seeders/central), one DB.
 *   --tenant       → tenant seeders (database/seeders/tenant), run in EACH tenant DB.
 *   --tenant=ID    → same, limited to one tenant.
 * The command signature must therefore declare `{--central : ...}` and `{--tenant= : ...}`.
 */
trait InteractsWithTrackedSeeders
{
    /** True while the current run targets tenant DBs (vs the central DB). */
    protected bool $seedsTenants = false;

    /**
     * Directory holding the tracked seeder files for the active target.
     */
    protected function seederDir(): string
    {
        return database_path($this->seedsTenants ? 'seeders/tenant' : 'seeders/central');
    }

    /**
     * Whether this run targets tenant DBs:
     *   --central (flag) → false, always wins.
     *   --tenant (flag)  → true.
     *   neither          → config('blueprint.seeders.default_target') === 'tenant'.
     */
    protected function targetsTenants(): bool
    {
        if ($this->input->hasParameterOption('--central')) {
            return false;
        }

        if ($this->input->hasParameterOption('--tenant')) {
            return true;
        }

        return config('blueprint.seeders.default_target', 'central') === 'tenant';
    }

    /**
     * Run $work against the correct target(s) and return an exit code:
     *   central (default) → once, against the shared/central DB.
     *   --tenant          → inside EACH tenant's DB (or one, with --tenant=ID).
     *
     * $work reads seederDir()/the Seeder model, which resolve to whichever DB
     * is active — so the same body seeds central or a tenant unchanged.
     */
    protected function eachSeederTarget(Closure $work): int
    {
        if (! $this->targetsTenants()) {
            $this->seedsTenants = false;
            $work();

            return Command::SUCCESS;
        }

        $this->seedsTenants = true;

        $model = config('tenancy.tenant_model');

        if (! $model || ! class_exists($model)) {
            $this->warn('No tenant model configured (tenancy.tenant_model) — nothing to do.');

            return Command::SUCCESS;
        }

        $query = $model::query();

        if (($id = $this->option('tenant')) !== null && $id !== '') {
            $query->whereKey($id);
        }

        $tenants = $query->get();

        if ($tenants->isEmpty()) {
            $this->warn('No tenants found.');

            return Command::SUCCESS;
        }

        foreach ($tenants as $tenant) {
            $this->info("→ tenant [{$tenant->getTenantKey()}]");
            $tenant->run(fn () => $work());
        }

        return Command::SUCCESS;
    }

    /**
     * All timestamp-prefixed seeders on disk, sorted oldest-first.
     * Each entry: ['filename' => string, 'timestamp' => int, 'class' => string].
     */
    protected function discoverSeeders(): Collection
    {
        $dir = $this->seederDir();

        if (! is_dir($dir)) {
            return collect();
        }

        return collect(scandir($dir))
            ->filter(fn ($file) => pathinfo($file, PATHINFO_EXTENSION) === 'php')
            ->map(function ($file) {
                $filename = pathinfo($file, PATHINFO_FILENAME);

                if (! str_contains($filename, '_')) {
                    return null;
                }

                [$prefix, $rest] = explode('_', $filename, 2);

                $timestamp = match (true) {
                    (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $prefix) => (int) strtotime($prefix),
                    is_numeric($prefix) => (int) $prefix,
                    default => null, // not a tracked/timestamped seeder — skip
                };

                return $timestamp === null
                    ? null
                    : ['filename' => $filename, 'timestamp' => $timestamp, 'class' => $rest];
            })
            ->filter()
            ->sortBy('timestamp')
            ->values();
    }

    /**
     * Include a seeder file and return its instance, or null if the file is
     * missing (e.g. tracked in the DB but deleted from disk).
     */
    protected function loadInstance(string $filename): ?object
    {
        $path = $this->seederDir().'/'.$filename.'.php';

        if (! is_file($path)) {
            return null;
        }

        $instance = include $path;

        return is_object($instance) ? $instance : null;
    }

    /**
     * Run the forward migration for a seeder. Prefers up(); falls back to the
     * legacy run() so un-migrated seeders still seed forward.
     */
    protected function callUp(object $instance): void
    {
        if (method_exists($instance, 'up')) {
            $instance->up();

            return;
        }

        $instance->run();
    }

    /**
     * Run the reverse migration for a seeder. Returns false when the seeder
     * declares no down() — the caller should warn and untrack only.
     */
    protected function callDown(object $instance): bool
    {
        if (! method_exists($instance, 'down')) {
            return false;
        }

        $instance->down();

        return true;
    }

    /**
     * The next batch number = current max + 1 (starts at 1 on an empty table).
     */
    protected function nextBatch(): int
    {
        return (int) Seeder::max('batch') + 1;
    }

    /**
     * Roll back one tracked batch: run each seeder's down() in reverse
     * timestamp order, then remove its tracking row. A seeder with no down()
     * cannot revert its data, so it is left tracked (its data is still applied)
     * and only warned about — never untracked, never a hard fail. A seeder whose
     * file is gone is untracked (the seeder no longer exists to re-run).
     */
    protected function rollbackBatch(int $batch): void
    {
        $rows = Seeder::where('batch', $batch)->get();

        if ($rows->isEmpty()) {
            $this->line("  Batch {$batch}: nothing to roll back.");

            return;
        }

        // Reverse-timestamp order so newer seeders unwind before older ones.
        $order = $this->discoverSeeders()->mapWithKeys(fn ($s) => [$s['filename'] => $s['timestamp']]);
        $ordered = $rows->sortByDesc(fn ($row) => $order[$row->seeder] ?? 0)->values();

        $this->info("Rolling back batch {$batch}…");

        foreach ($ordered as $row) {
            $class = explode('_', $row->seeder, 2)[1] ?? $row->seeder;

            $instance = $this->loadInstance($row->seeder);

            if ($instance === null) {
                $row->delete();
                $this->warn("  ⚠ {$class}: file missing — untracked only, no data reverted.");

                continue;
            }

            try {
                if ($this->callDown($instance)) {
                    $row->delete();
                    $this->info("  ✓ rolled back {$class}");

                    continue;
                }

                // No down(): data cannot be reverted, so keep the tracking row
                // (it still reflects that the seeder's data is applied).
                $this->warn("  ⚠ {$class}: no down() — skipped, still tracked (data not reverted).");
            } catch (Throwable $e) {
                $this->error("  ✗ {$class}: ".$e->getMessage());
            }
        }
    }
}
