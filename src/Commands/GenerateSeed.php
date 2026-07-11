<?php

namespace Mohab\LaravelBlueprint\Commands;

use Mohab\LaravelBlueprint\Models\Seeder;
use Illuminate\Console\Command;

class GenerateSeed extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'generate:seed';

    /**
     * The console command description.
     */
    protected $description = 'Run unapplied seeders from database/seeders/RunningSeeder';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('Starting seeding process...');

        $seederPath = database_path('seeders/RunningSeeder');

        if (!is_dir($seederPath)) {
            $this->warn("Seeder directory not found: {$seederPath}");
            return;
        }

        // Get all PHP files from the seeders directory
        $allSeederFiles = array_diff(scandir($seederPath), ['.', '..']);

        // Filter only .php files and extract class names with timestamps
        $allSeederClasses = collect($allSeederFiles)
            ->filter(fn($file) => pathinfo($file, PATHINFO_EXTENSION) === 'php')
            ->map(function ($file) {
                $filename = pathinfo($file, PATHINFO_FILENAME);

                $timestamp = 0;
                $className = $filename;

                // Extract the timestamp prefix if present.
                // Files are created as TIMESTAMP_ClassName (see MakeSeederCommand),
                // and legacy YYYY-MM-DD_ClassName files are supported too.
                if (str_contains($filename, '_')) {
                    [$prefix, $rest] = explode('_', $filename, 2);

                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $prefix)) {
                        // Date prefix — convert to a unix timestamp for sorting.
                        $timestamp = (int) strtotime($prefix);
                        $className = $rest;
                    } elseif (is_numeric($prefix)) {
                        $timestamp = (int) $prefix;
                        $className = $rest;
                    }
                }

                return [
                    'filename'  => $filename,
                    'timestamp' => $timestamp,
                    'class'     => $className,
                ];
            })
            ->sortBy('timestamp')
            ->values()
            ->toArray();

        if (empty($allSeederClasses)) {
            $this->warn('No seeder files found in ' . $seederPath);
            return;
        }

        $appliedSeeders = Seeder::all()->pluck('seeder')->toArray();

        // Find unapplied seeders (maintaining order)
        $unAppliedSeeders = collect($allSeederClasses)
            ->filter(fn($seeder) => !in_array($seeder['filename'], $appliedSeeders))
            ->values()
            ->toArray();

        if (count($unAppliedSeeders) > 0) {
            $this->info("  Found " . count($unAppliedSeeders) . " new seeder(s)");

            foreach ($unAppliedSeeders as $seeder) {
                $seederFilename = $seeder['filename'];
                $seederClass    = $seeder['class'];
                $timestamp      = $seeder['timestamp'];

                if (!Seeder::where('seeder', $seederFilename)->exists()) {
                    try {
                        $dateFormatted = $timestamp > 0 ? date('Y-m-d H:i:s', $timestamp) : 'No timestamp';
                        $this->info("  Running {$seederClass} ({$dateFormatted})...");

                        $filePath      = database_path('seeders/RunningSeeder/' . $seederFilename . '.php');
                        $seederInstance = include $filePath;
                        $seederInstance->run();

                        $this->info("  ✓ {$seederClass} has been run");
                        Seeder::create(['seeder' => $seederFilename]);
                    } catch (\Exception $e) {
                        $this->error("  ✗ Failed to run {$seederClass}: " . $e->getMessage());
                    }
                }
            }
        } else {
            $this->info("  No new seeders to run");
        }

        $this->info('✓ Seeding process completed!');
    }
}
