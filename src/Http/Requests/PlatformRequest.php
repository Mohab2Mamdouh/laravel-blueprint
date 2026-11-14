<?php

namespace BlueprintPhp\Laravel\Http\Requests;

use BlueprintPhp\Laravel\Contracts\HasPlatform;
use BlueprintPhp\Laravel\Enums\Platform;
use BlueprintPhp\Laravel\Enums\Version;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

abstract class PlatformRequest extends FormRequest implements HasPlatform
{
    abstract public static function platform(): Platform;

    abstract public static function version(): Version;

    /**
     * Resolve the validator and enrich it with reader-friendly attribute names
     * so nested / locale-suffixed keys read naturally in error messages, e.g.:
     *   "name.ar"                -> "The arabic name field is required."
     *   "channels.*.channel_id"  -> "The channel id field is required."
     */
    protected function getValidatorInstance(): ValidatorContract
    {
        $validator = parent::getValidatorInstance();

        $this->applyFriendlyAttributes($validator);

        return $validator;
    }

    protected function applyFriendlyAttributes(ValidatorContract $validator): void
    {
        if (! $validator instanceof Validator) {
            return;
        }

        $locales = $this->localeNames();

        foreach (array_keys($validator->getRules()) as $key) {
            // Respect attributes a request declared explicitly or via lang files.
            if (isset($validator->customAttributes[$key]) || $this->hasLangAttribute($key)) {
                continue;
            }

            $label = $this->friendlyAttribute($key, $locales);

            if ($label !== null) {
                $validator->customAttributes[$key] = $label;
            }
        }
    }

    protected function friendlyAttribute(string $key, array $locales): ?string
    {
        $segments = explode('.', $key);
        $last = end($segments);

        // Locale-suffixed translatable field: "name.ar" -> "arabic name".
        if (isset($locales[$last]) && count($segments) > 1) {
            array_pop($segments);

            return $this->formatTranslatableAttribute($segments, $locales[$last]);
        }

        // Nested / array-item field: "channels.*.channel_id" -> "channel id".
        // Simple flat keys are left to Laravel's own humanizer.
        if (str_contains($key, '.')) {
            return $this->humanizeSegments($segments);
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    protected function localeNames(): array
    {
        $locales = trans('validation.locales');

        return is_array($locales) ? $locales : ['ar' => 'arabic', 'en' => 'english'];
    }

    /**
     * @param  array<int, string>  $baseSegments
     */
    protected function formatTranslatableAttribute(array $baseSegments, string $localeName): string
    {
        $base = implode('.', $baseSegments);

        $attributes = trans('validation.attributes');
        $attribute = is_array($attributes) && isset($attributes[$base]) && is_string($attributes[$base])
            ? $attributes[$base]
            : $this->humanizeSegments($baseSegments);

        $formatted = trans('validation.translatable_attribute', [
            'locale' => $localeName,
            'attribute' => $attribute,
        ]);

        // Fall back gracefully if the lang key is missing.
        return $formatted === 'validation.translatable_attribute'
            ? trim("{$localeName} {$attribute}")
            : $formatted;
    }

    /**
     * Humanize a dotted attribute path to its last meaningful segment,
     * dropping array indices and wildcards. "channels.*.channel_id" -> "channel id".
     *
     * @param  array<int, string>  $segments
     */
    protected function humanizeSegments(array $segments): string
    {
        $meaningful = array_values(array_filter(
            $segments,
            fn ($segment) => $segment !== '*' && ! ctype_digit($segment)
        ));

        $field = end($meaningful);

        if ($field === false) {
            $field = end($segments);
        }

        return str_replace('_', ' ', Str::snake((string) $field));
    }

    /**
     * Whether a lang file already defines a friendly name for this key
     * (either exactly or via its wildcard form), which we must not override.
     */
    protected function hasLangAttribute(string $key): bool
    {
        $attributes = trans('validation.attributes');

        if (! is_array($attributes)) {
            return false;
        }

        // Match Laravel's own resolution: attribute overrides are stored as
        // flat keys (e.g. "display_name.ar"), not dot-nested, and array items
        // resolve via their wildcard form.
        foreach (array_unique([$key, $this->wildcardKey($key)]) as $candidate) {
            if (array_key_exists($candidate, $attributes)) {
                return true;
            }
        }

        return false;
    }

    protected function wildcardKey(string $key): string
    {
        return implode('.', array_map(
            fn ($segment) => ctype_digit($segment) ? '*' : $segment,
            explode('.', $key)
        ));
    }
}
