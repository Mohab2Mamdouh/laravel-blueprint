<?php

declare(strict_types=1);

namespace BlueprintPhp\Laravel\Traits;

use Illuminate\Database\Eloquent\Attributes\Scope;
use ReflectionClass;
use ReflectionMethod;
use Spatie\Translatable\HasTranslations;

/**
 * Gives a model a single, ordered source of truth for its filterable scopes. The
 * repository consults it to apply incoming request scopes deterministically
 * instead of in arbitrary request order; the scopes endpoint advertises them.
 *
 * Override hiddenScopes() / orderedScopeNames() / requiredScopes() per model.
 */
trait AcceptedScopes
{
    /**
     * @return list<string>
     */
    public function acceptedScopes(): array
    {
        $scopes = array_diff($this->discoverScopes(), $this->hiddenScopes());

        return $this->sortScopesByOrder(array_values($scopes));
    }

    /**
     * Reorder an incoming scopes array to follow acceptedScopes(); unknown names kept at the end.
     *
     * @param  array<int|string,mixed>  $scopes
     * @return array<int|string,mixed>
     */
    public function orderScopes(array $scopes): array
    {
        $position = array_flip($this->acceptedScopes());

        $entries = [];
        foreach ($scopes as $key => $value) {
            $name = is_int($key) ? $value : $key;
            $entries[] = [$key, $value, $position[$name] ?? PHP_INT_MAX];
        }

        usort($entries, fn (array $a, array $b) => $a[2] <=> $b[2]);

        $ordered = [];
        foreach ($entries as [$key, $value]) {
            is_int($key) ? $ordered[] = $value : $ordered[$key] = $value;
        }

        return $ordered;
    }

    /**
     * Scopes the listing must be anchored to (client passes exactly one). Empty = none.
     *
     * @return list<string>
     */
    public function requiredScopes(): array
    {
        return [];
    }

    /**
     * Scopes omitted from acceptedScopes() (still usable, just not advertised). Call
     * array_merge(parent::hiddenScopes(), [...]) when overriding to keep the auto-exclusions.
     *
     * @return list<string>
     */
    protected function hiddenScopes(): array
    {
        $hidden = [];

        // Hide Spatie Translatable locale scopes on translatable models.
        if (in_array(HasTranslations::class, class_uses_recursive(static::class), true)) {
            $hidden = array_merge($hidden, [
                'whereLocale', 'whereLocales', 'whereJsonContainsLocale', 'whereJsonContainsLocales',
            ]);
        }

        return $hidden;
    }

    /**
     * Explicit application order by scope name; unlisted keep natural order.
     *
     * @return list<string>
     */
    protected function orderedScopeNames(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    protected function discoverScopes(): array
    {
        $scopes = [];

        foreach ((new ReflectionClass($this))->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED) as $method) {
            $name = $method->getName();

            if ($method->getAttributes(Scope::class) !== []) {
                $scopes[] = $name;
            } elseif (str_starts_with($name, 'scope') && strlen($name) > 5) {
                $scopes[] = lcfirst(substr($name, 5));
            }
        }

        return array_values(array_unique($scopes));
    }

    /**
     * @param  list<string>  $scopes
     * @return list<string>
     */
    protected function sortScopesByOrder(array $scopes): array
    {
        $position = array_flip($this->orderedScopeNames());

        usort($scopes, fn (string $a, string $b) => ($position[$a] ?? PHP_INT_MAX) <=> ($position[$b] ?? PHP_INT_MAX));

        return $scopes;
    }
}
