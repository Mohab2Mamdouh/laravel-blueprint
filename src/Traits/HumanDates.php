<?php

namespace Mohab2Mamdouh\LaravelStarter\Traits;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait HumanDates
{
    private function formattedTimestamp(mixed $value): ?string
    {
        return $value
            ? Carbon::parse($value)
                ->timezone(config('app.timezone'))
                ->locale(app()->getLocale())
                ->translatedFormat('d M, Y, h:i A')
            : null;
    }

    private function attributeDateTime(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $this->formattedTimestamp($value),
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
