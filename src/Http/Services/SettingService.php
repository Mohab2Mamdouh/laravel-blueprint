<?php

namespace Mohab2Mamdouh\LaravelStarter\Http\Services;

use Mohab2Mamdouh\LaravelStarter\Http\Requests\SettingRequest;
use Mohab2Mamdouh\LaravelStarter\Repository\Contracts\SettingRepositoryInterface;
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
    public function edit(): View
    {
        $settings = $this->repository->query()->pluck('value', 'key')->toArray();

        return view('starter::settings.form', compact('settings'));
    }

    /**
     * Persist the validated settings data.
     */
    public function update(SettingRequest $request): RedirectResponse
    {
        $data = $request->validated();

        foreach ($data as $key => $value) {
            if ($value instanceof UploadedFile) {
                $value = 'storage/settings/' . $value->store('settings', 'public');
            }
            $this->repository->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        if (!app()->environment('local')) {
            Cache::forget('view.websiteSettings');
        }

        return redirect()->back()->with('success', 'Settings updated successfully');
    }

    /**
     * Trigger a full application backup download.
     */
    public function createBackup(): mixed
    {
        return $this->repository->createBackup();
    }
}
