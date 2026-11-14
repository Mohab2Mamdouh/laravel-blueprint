<?php

declare(strict_types=1);

namespace BlueprintPhp\Laravel\Scopes;

use BlueprintPhp\Laravel\Traits\AcceptedScopes;
use Illuminate\Database\Eloquent\Model;

/**
 * Map of `entity key => model class`, backing the generic scopes endpoint.
 * Register scopeable models in a provider; the endpoint resolves each by key.
 */
class ScopeableRegistry
{
    /**
     * @param  array<string, class-string<Model>>  $map
     */
    public function __construct(private array $map = []) {}

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function register(string $entity, string $modelClass): void
    {
        $this->map[$entity] = $modelClass;
    }

    public function has(string $entity): bool
    {
        return isset($this->map[$entity]);
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->map);
    }

    /**
     * Model instance for an entity key, or null if unknown / not scope-listable.
     */
    public function model(string $entity): ?Model
    {
        if (! $this->has($entity)) {
            return null;
        }

        $model = app($this->map[$entity]);

        return in_array(AcceptedScopes::class, class_uses_recursive($model), true) ? $model : null;
    }
}
