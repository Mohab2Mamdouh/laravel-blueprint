<?php

namespace Mohab\LaravelBlueprint\Traits;

trait HasMeta
{
    // -------------------------------------------------------------------------
    // Configuration
    // -------------------------------------------------------------------------

    /**
     * The JSON column that stores meta key→value pairs.
     * Override in your model or declare a $metaColumn property.
     */
    protected function metaColumn(): string
    {
        return $this->metaColumn ?? 'meta';
    }

    // -------------------------------------------------------------------------
    // Read
    // -------------------------------------------------------------------------

    /**
     * Get the entire meta array.
     *
     * @return array<string, mixed>
     */
    public function getAllMeta(): array
    {
        return (array) ($this->{$this->metaColumn()} ?? []);
    }

    /**
     * Get a single meta value by key, with an optional default.
     */
    public function getMeta(string $key, mixed $default = null): mixed
    {
        return data_get($this->getAllMeta(), $key, $default);
    }

    public function hasMeta(string $key): bool
    {
        return array_key_exists($key, $this->getAllMeta());
    }

    // -------------------------------------------------------------------------
    // Write
    // -------------------------------------------------------------------------

    /**
     * Set a single meta key and persist immediately.
     */
    public function setMeta(string $key, mixed $value): bool
    {
        $meta       = $this->getAllMeta();
        $meta[$key] = $value;

        return $this->persistMeta($meta);
    }

    /**
     * Merge an array of key→value pairs into the existing meta and persist.
     *
     * @param  array<string, mixed> $values
     */
    public function mergeMeta(array $values): bool
    {
        return $this->persistMeta(
            array_merge($this->getAllMeta(), $values)
        );
    }

    /**
     * Remove a single key from meta and persist.
     */
    public function forgetMeta(string $key): bool
    {
        $meta = $this->getAllMeta();
        unset($meta[$key]);

        return $this->persistMeta($meta);
    }

    /**
     * Wipe all meta and persist.
     */
    public function flushMeta(): bool
    {
        return $this->persistMeta([]);
    }

    // -------------------------------------------------------------------------
    // Internal
    // -------------------------------------------------------------------------

    private function persistMeta(array $meta): bool
    {
        $this->{$this->metaColumn()} = $meta;
        return $this->save();
    }
}