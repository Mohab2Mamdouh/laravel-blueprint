<?php

namespace Mohab2Mamdouh\LaravelStarter\Http\Controllers;

use Illuminate\Routing\Controller;
use Mohab2Mamdouh\LaravelStarter\Http\Requests\SettingRequest;
use Mohab2Mamdouh\LaravelStarter\Http\Services\SettingService;
use Illuminate\Contracts\View\View;

class SettingController extends Controller
{
    public function __construct(
        private readonly SettingService $service
    ) {}

    /**
     * Show the settings edit form.
     */
    public function edit(): View
    {
        return $this->service->edit();
    }

    /**
     * Update application settings.
     */
    public function update(SettingRequest $request)
    {
        return $this->service->update($request);
    }

    /**
     * Download a full application backup.
     */
    public function createBackup()
    {
        return $this->service->createBackup();
    }
}
