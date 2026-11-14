<?php

namespace BlueprintPhp\Laravel\Commands;

use BlueprintPhp\Laravel\Commands\Concerns\InteractsWithTrackedSeeders;
use BlueprintPhp\Laravel\Models\Seeder;
use Illuminate\Console\Command;
use Throwable;

/**
 * Runs unapplied, timestamp-prefixed tracked seeders in order, recording each
 * in the `seeders` table so it never re-runs. Everything applied in a single
 * invocation shares one batch number, so `seed:rollback` can undo a run as a
 * unit — much like migrations.
 *
 *   seed                → run every pending central seeder as a new batch.
 *   seed --tenant       → same, but per tenant DB (database/seeders/tenant).
 *   seed --tenant=ID    → same, limited to one tenant.
 *   seed:rollback       → undo the most recent batch (calls each down()).
 *   seed:reset          → undo every batch.
 *   seed:status         → show applied/pending seeders and their batch.
 *
 * Non-timestamped files (no numeric/date prefix) are ignored here — they are
 * invoked directly elsewhere, not tracked.
 */
class SeedCommand extends Command
{
    use InteractsWithTrackedSeeders;

    protected $signature = 'seed
                            {--central : Seed the central DB (database/seeders/central)}
                            {--tenant= : Seed tenant DBs (database/seeders/tenant); optional value limits to one tenant id}';

    protected $description = 'Run unapplied tracked seeders as a new batch (target from blueprint.seeders.default_target; --central/--tenant override).';

    public function handle(): int
    {
        return $this->eachSeederTarget(fn () => $this->seed());
    }

    private function seed(): void
    {
        $seeders = $this->discoverSeeders();

        if ($seeders->isEmpty()) {
            $this->line('No seeder files.');

            return;
        }

        $applied = Seeder::all()->pluck('seeder')->all();
        $pending = $seeders->reject(fn ($s) => in_array($s['filename'], $applied, true))->values();

        if ($pending->isEmpty()) {
            $this->line('Nothing new — all seeders already applied.');

            return;
        }

        $batch = $this->nextBatch();
        $this->info("Seeding (batch {$batch})…");

        foreach ($pending as $seeder) {
            try {
                $this->info("  Running {$seeder['class']}…");

                $instance = $this->loadInstance($seeder['filename']);

                if ($instance === null) {
                    $this->error("  ✗ {$seeder['class']}: file could not be loaded.");

                    continue;
                }

                $this->callUp($instance);
                Seeder::create(['seeder' => $seeder['filename'], 'batch' => $batch]);
                $this->info("  ✓ {$seeder['class']}");
            } catch (Throwable $e) {
                $this->error("  ✗ {$seeder['class']}: ".$e->getMessage());
            }
        }

        $this->info('✓ Seeding completed.');
    }
}
