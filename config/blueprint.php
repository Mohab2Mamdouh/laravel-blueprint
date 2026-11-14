<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Supported Locales
    |--------------------------------------------------------------------------
    | The locales that the SetLocale middleware will accept. Any locale not
    | in this list will be ignored and the app default will be used instead.
    */
    'supported_locales' => ['en', 'ar'],

    /*
    |--------------------------------------------------------------------------
    | Auto Publish
    |--------------------------------------------------------------------------
    | When true the package will attempt to copy config and lang files into
    | the consuming application automatically if they are missing. This can
    | be disabled by setting `blueprint.auto_publish` to false in the app.
    */
    'auto_publish' => true,

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    | Configuration for the package's logging helpers. `max_trace_length`
    | controls how many characters of an exception trace are retained when
    | the package writes exception traces to the log (to avoid very large
    | log entries). Set to `null` to keep full traces.
    */
    'logging' => [
        'max_trace_length' => 5000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Settings
    |--------------------------------------------------------------------------
    | Where the getSettings() helper reads application settings from.
    | - `repository`: a class or container-bound interface exposing
    |   `settings(): array` (see BlueprintPhp\Laravel\Contracts\ProvidesSettings).
    |   Leave null when the app has no settings store — getSettings() then
    |   returns an empty array.
    | - `table`: the settings table; nothing is read until it exists.
    */
    'settings' => [
        'repository' => null,
        'table' => 'settings',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tracked seeders
    |--------------------------------------------------------------------------
    | Which database the `seed`, `seed:*` and `make:seeder` commands target when
    | neither --central nor --tenant is passed: 'central' reads
    | database/seeders/central, 'tenant' reads database/seeders/tenant and runs
    | in every tenant database (requires stancl/tenancy).
    */
    'seeders' => [
        'default_target' => env('SEEDER_TARGET', 'central'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    | Settings related to caching values the package exposes to views.
    | - `enabled`: When null the provider will fall back to its default
    |   behaviour (`!app()->environment('local')`). Set to `true` or `false`
    |   to override.
    | - `website_settings_key`: Cache key used when storing resolved
    |   website settings via the package view composer.
    | - `remember_forever`: When true the package uses `Cache::rememberForever`
    |   to persist the cached value, otherwise it will use `Cache::remember`
    |   (consumers must define a ttl when `false`).
    */
    'cache' => [
        'enabled' => null,
        'website_settings_key' => 'view.websiteSettings',
        'remember_forever' => true,
        // TTL in seconds used when `remember_forever` is false
        'ttl' => 3600,
    ],

];
