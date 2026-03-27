<?php

namespace Mohab2Mamdouh\LaravelStarter\Http\Requests;

use Mohab2Mamdouh\LaravelStarter\Repository\Contracts\SettingRepositoryInterface;
use Illuminate\Foundation\Http\FormRequest;

class SettingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules from the settings table.
     */
    public function rules(): array
    {
        return resolve(SettingRepositoryInterface::class)->settingsKeysValidation();
    }
}
