<?php

namespace BlueprintPhp\Laravel\Contracts;

/**
 * A source of application settings for the global getSettings() helper.
 *
 * Implement it on your settings repository and point
 * `blueprint.settings.repository` at the class (or at an interface bound
 * in the container).
 */
interface ProvidesSettings
{
    /**
     * Every setting as a flat `['key' => value, …]` map.
     *
     * @return array<string, mixed>
     */
    public function settings(): array;
}
