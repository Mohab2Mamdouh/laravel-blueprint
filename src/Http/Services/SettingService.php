<?php

namespace Mohab\LaravelBlueprint\Http\Services;

use Mohab\LaravelBlueprint\Http\Requests\SettingRequest;
use Mohab\LaravelBlueprint\Repository\Contracts\SettingRepositoryInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;

readonly class SettingService
{
    public function __construct(
        private SettingRepositoryInterface $repository
    ) {}

    /**
     * Return the settings edit view.
     */
    public function settings(): array
    {
        return $this->repository->settings();
    }

    /**
     * Persist the validated settings data.
     */
    public function update(SettingRequest $request): bool
    {
        $data = $request->validated();

        foreach ($data as $key => $value) {
            if ($value instanceof UploadedFile) {
                $value = 'storage/settings/' . $value->store('settings', 'public');
            }
            $this->repository->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        if (!app()->environment('local')) {
            Cache::forget(config('blueprint.cache.website_settings_key', 'view.websiteSettings'));
        }

        return true;
    }

    /**
     * Trigger a full application backup download.
     */
    public function createBackup(): mixed
    {
        return $this->repository->createBackup();
    }
}
