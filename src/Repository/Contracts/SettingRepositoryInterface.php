<?php

namespace Mohab2Mamdouh\LaravelStarter\Repository\Contracts;

interface SettingRepositoryInterface extends RepositoryInterface
{
    /**
     * Return all settings as a key-value array.
     */
    public function settings(): array;

    /**
     * Return the validation rules for all settings keys.
     */
    public function settingsKeysValidation(): array;

    /**
     * Create a full backup archive and return a download response.
     */
    public function createBackup(): mixed;
}
