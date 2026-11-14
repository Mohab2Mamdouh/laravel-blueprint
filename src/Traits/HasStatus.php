<?php

namespace BlueprintPhp\Laravel\Traits;

use Illuminate\Database\Eloquent\Builder;

trait HasStatus
{
    // -------------------------------------------------------------------------
    // Configuration
    // -------------------------------------------------------------------------

    /**
     * The column that stores the status value.
     * Override in your model or declare a $statusColumn property.
     */
    protected function statusColumn(): string
    {
        return $this->statusColumn ?? 'status';
    }

    /**
     * The value considered "active".
     * Override in your model or declare a $activeValue property.
     */
    protected function activeValue(): string
    {
        return $this->activeValue ?? 'active';
    }

    /**
     * The value considered "inactive".
     * Override in your model or declare an $inactiveValue property.
     */
    protected function inactiveValue(): string
    {
        return $this->inactiveValue ?? 'inactive';
    }

    // -------------------------------------------------------------------------
    // State checks
    // -------------------------------------------------------------------------

    public function isActive(): bool
    {
        return $this->{$this->statusColumn()} === $this->activeValue();
    }

    public function isInactive(): bool
    {
        return ! $this->isActive();
    }

    // -------------------------------------------------------------------------
    // State transitions
    // -------------------------------------------------------------------------

    public function activate(): bool
    {
        return $this->setStatus($this->activeValue());
    }

    public function deactivate(): bool
    {
        return $this->setStatus($this->inactiveValue());
    }

    public function toggleStatus(): bool
    {
        return $this->isActive() ? $this->deactivate() : $this->activate();
    }

    /**
     * Set an arbitrary status value and persist immediately.
     */
    public function setStatus(string $status): bool
    {
        $this->{$this->statusColumn()} = $status;

        return $this->save();
    }

    // -------------------------------------------------------------------------
    // Query scopes
    // -------------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where($this->statusColumn(), $this->activeValue());
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where($this->statusColumn(), $this->inactiveValue());
    }

    /**
     * Scope to any specific status value.
     *
     * Usage:  Product::withStatus('draft')->get();
     */
    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where($this->statusColumn(), $status);
    }
}
