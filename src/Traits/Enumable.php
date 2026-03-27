<?php

namespace Mohab\LaravelBlueprint\Traits;

trait Enumable
{
    /**
     * Return all enum case values as an array.
     */
    public static function values(): array
    {
        $values = [];

        foreach (self::cases() as $case) {
            $values[] = $case->value;
        }

        return $values;
    }

    /**
     * Get an enum case by its constant name.
     *
     * @throws \ValueError if no matching case is found
     */
    public static function fromName(string $name): static
    {
        foreach (self::cases() as $case) {
            if ($case->name === $name) {
                return $case;
            }
        }

        throw new \ValueError("'{$name}' is not a valid name for enum " . static::class);
    }
}
