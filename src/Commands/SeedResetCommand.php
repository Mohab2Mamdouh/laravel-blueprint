<?php

namespace BlueprintPhp\Laravel\Commands;

use BlueprintPhp\Laravel\Commands\Concerns\InteractsWithTrackedSeeders;
use BlueprintPhp\Laravel\Models\Seeder;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;

/**
 * Roll back every tracked seeder batch (newest batch first). Mirrors
 * `migrate:reset`. Same per-seeder rollback caveat as seed:rollback.
 */
class SeedResetCommand extends Command
{
    use ConfirmableTrait;
    use InteractsWithTrackedSeeders;

    protected $signature = 'seed:reset
                            {--central : Reset the central DB (database/seeders/central)}
                            {--tenant= : Reset tenant DBs (database/seeders/tenant); optional value limits to one tenant id}
                            {--force : Force the operation to run when in production}';

    protected $description = 'Roll back every applied tracked seeder batch.';

    public function handle(): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        return $this->eachSeederTarget(fn () => $this->reset());
    }

    private function reset(): void
    {
        $batches = Seeder::query()
            ->select('batch')
            ->distinct()
            ->orderByDesc('batch')
            ->pluck('batch');

        if ($batches->isEmpty()) {
            $this->info('Nothing to roll back.');

            return;
        }

        foreach ($batches as $batch) {
            $this->rollbackBatch((int) $batch);
        }

        $this->info('✓ Reset completed.');
    }
}
