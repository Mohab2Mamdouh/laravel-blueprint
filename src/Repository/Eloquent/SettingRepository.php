<?php

namespace Mohab\LaravelBlueprint\Repository\Eloquent;

use Mohab\LaravelBlueprint\Models\Setting;
use Mohab\LaravelBlueprint\Repository\Contracts\SettingRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SettingRepository extends Repository implements SettingRepositoryInterface
{
    protected Model $model;

    public function __construct(Setting $model)
    {
        parent::__construct($model);
    }

    public function settings(): array
    {
        return $this->query()->pluck('value', 'key')->toArray();
    }

    public function settingsKeysValidation(): array
    {
        $settings = $this->query()->pluck('validation', 'key');

        $rules = [];

        $settings->each(function ($validation, $key) use (&$rules) {
            if (is_string($validation)) {
                $rules[$key] = $validation;
            } elseif (is_array($validation)) {
                foreach ($validation as $subKey => $rule) {
                    if ($subKey === 'self') {
                        $rules[$key] = $rule;
                    } else {
                        $rules["{$key}.{$subKey}"] = $rule;
                    }
                }
            }
        });

        return $rules;
    }

    public function createBackup(): mixed
    {
        $siteName  = $this->getSiteName();
        $timestamp = now()->format('Y-m-d_H-i-s');
        $backupName = "{$siteName}_{$timestamp}";

        $tempPath = storage_path("app/temp_backup_{$timestamp}");
        File::makeDirectory("{$tempPath}/db", 0755, true);
        File::makeDirectory("{$tempPath}/storage", 0755, true);

        $this->backupDatabase("{$tempPath}/db/database.sql");
        $this->backupStorage("{$tempPath}/storage");

        $archivePath = storage_path("app/{$backupName}.backup.gz");
        $this->createTarGz($tempPath, $archivePath);

        $this->updateLastBackup();

        File::deleteDirectory($tempPath);

        return response()->download($archivePath)->deleteFileAfterSend(true);
    }

    protected function backupDatabase(string $outputPath): void
    {
        $database = config('database.connections.mysql.database');
        $tables   = DB::select('SHOW TABLES');
        $tableKey = 'Tables_in_' . $database;

        $sql = "-- Database Backup\n-- Generated: " . now() . "\n\nSET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $table) {
            $tableName = $table->$tableKey;
            $sql      .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
            $sql      .= DB::select("SHOW CREATE TABLE `{$tableName}`")[0]->{'Create Table'} . ";\n\n";

            $rows = DB::table($tableName)->get();
            foreach ($rows as $row) {
                $values = array_map(fn($v) => is_null($v) ? 'NULL' : "'" . addslashes($v) . "'", (array) $row);
                $sql   .= "INSERT INTO `{$tableName}` VALUES (" . implode(', ', $values) . ");\n";
            }
            $sql .= "\n";
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        File::put($outputPath, $sql);
    }

    protected function backupStorage(string $outputPath): void
    {
        $storagePath = storage_path('app/public');
        if (File::exists($storagePath)) {
            File::copyDirectory($storagePath, $outputPath);
        }
    }

    protected function createTarGz(string $sourcePath, string $archivePath): void
    {
        $phar = new \PharData($archivePath);
        $phar->buildFromDirectory($sourcePath);
        $phar->compress(\Phar::GZ);

        // PharData creates <name>.tar first; clean it up if it exists
        $tarPath = preg_replace('/\.gz$/', '', $archivePath);
        if (file_exists($tarPath) && $tarPath !== $archivePath) {
            @unlink($tarPath);
        }
    }

    protected function updateLastBackup(): void
    {
        $this->updateOrCreate(['key' => 'last_backup'], ['value' => now()->format('Y-m-d')]);

        if (!app()->environment('local')) {
            Cache::forget('view.websiteSettings');
        }
    }

    protected function getSiteName(): string
    {
        $setting  = $this->first('key', 'site_name');
        $siteName = $setting?->value['en'] ?? 'backup';
        return preg_replace('/[^A-Za-z0-9_\-]/', '_', $siteName);
    }
}
