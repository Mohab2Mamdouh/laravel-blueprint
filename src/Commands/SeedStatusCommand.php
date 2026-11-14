<?php

namespace BlueprintPhp\Laravel\Commands;

use BlueprintPhp\Laravel\Commands\Concerns\InteractsWithTrackedSeeders;
use BlueprintPhp\Laravel\Models\Seeder;
use Illuminate\Console\Command;

/**
 * Show every tracked seeder on disk with whether it has run and its batch.
 * Mirrors `migrate:status`.
 */
class SeedStatusCommand extends Command
{
    use InteractsWithTrackedSeeders;

    protected $signature = 'seed:status
                            {--central : Show the central DB (database/seeders/central)}
                            {--tenant= : Show tenant DBs (database/seeders/tenant); optional value limits to one tenant id}';

    protected $description = 'Show which tracked seeders have run and their batch.';

    public function handle(): int
    {
        return $this->eachSeederTarget(fn () => $this->status());
    }

    private function status(): void
    {
        $seeders = $this->discoverSeeders();

        if ($seeders->isEmpty()) {
            $this->line('No seeder files.');

            return;
        }

        $batches = Seeder::all()->keyBy('seeder');

        $rows = $seeders->map(function ($seeder) use ($batches) {
            $applied = $batches->get($seeder['filename']);

            return [
                $applied ? '<info>Ran</info>' : '<comment>Pending</comment>',
                $seeder['class'],
                $applied ? $applied->batch : '—',
            ];
        })->all();

        $this->table(['Status', 'Seeder', 'Batch'], $rows);
    }
}
