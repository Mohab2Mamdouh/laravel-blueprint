<?php

namespace BlueprintPhp\Laravel\Traits;

use BlueprintPhp\Laravel\Casts\FormattedDateTime;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Presents the audit timestamps exactly as the FormattedDateTime cast presents
 * domain datetimes: stored UTC, displayed in the app-wide settings timezone as
 * `Y-m-d h:iA`. It delegates rather than reimplementing, so a single payload can
 * never mix two timezones or two date formats.
 *
 * These accessors take precedence over any cast declared on the same column.
 */
trait HumanDates
{
    private function formattedTimestamp(mixed $value): ?string
    {
        return FormattedDateTime::getTime($value);
    }

    private function attributeDateTime(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->formattedTimestamp($value),
        );
    }

    protected function createdAt(): Attribute
    {
        return $this->attributeDateTime();
    }

    protected function updatedAt(): Attribute
    {
        return $this->attributeDateTime();
    }

    protected function emailVerifiedAt(): Attribute
    {
        return $this->attributeDateTime();
    }
}
