<?php

namespace BlueprintPhp\Laravel\Commands;

use BlueprintPhp\Laravel\Commands\Concerns\InteractsWithTrackedSeeders;
use BlueprintPhp\Laravel\Models\Seeder;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;

/**
 * Undo the most recently applied seeder batch(es), calling each seeder's
 * down(). Mirrors `migrate:rollback`. Rollback safety is per-seeder: a seeder
 * with an empty/absent down() is untracked with a warning but its data stays.
 */
class SeedRollbackCommand extends Command
{
    use ConfirmableTrait;
    use InteractsWithTrackedSeeders;

    protected $signature = 'seed:rollback
                            {--step=1 : Number of batches to roll back}
                            {--central : Roll back the central DB (database/seeders/central)}
                            {--tenant= : Roll back tenant DBs (database/seeders/tenant); optional value limits to one tenant id}
                            {--force : Force the operation to run when in production}';

    protected $description = 'Roll back the last tracked seeder batch (or --step=N batches).';

    public function handle(): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        return $this->eachSeederTarget(fn () => $this->rollback());
    }

    private function rollback(): void
    {
        $batches = Seeder::query()
            ->select('batch')
            ->distinct()
            ->orderByDesc('batch')
            ->limit(max(1, (int) $this->option('step')))
            ->pluck('batch');

        if ($batches->isEmpty()) {
            $this->info('Nothing to roll back.');

            return;
        }

        foreach ($batches as $batch) {
            $this->rollbackBatch((int) $batch);
        }

        $this->info('✓ Rollback completed.');
    }
}
