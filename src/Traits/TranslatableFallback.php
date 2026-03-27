<?php

namespace Mohab2Mamdouh\LaravelStarter\Traits;

use Spatie\Translatable\HasTranslations;

trait TranslatableFallback
{
    use HasTranslations {
        HasTranslations::getTranslation as getTranslationSpatie;
    }

    /**
     * Get translation with fallback to the first available language when the
     * requested locale is missing.
     */
    public function getTranslation(string $key, string $locale, bool $useFallbackLocale = true): mixed
    {
        $translation = $this->getTranslationSpatie($key, $locale, $useFallbackLocale);

        if ($translation) {
            return $translation;
        }

        $translations = $this->getTranslations($key);

        return array_values($translations)[0] ?? null;
    }
}
