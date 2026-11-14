<?php

namespace BlueprintPhp\Laravel\Casts;

use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Datetime cast with two precisions, chosen by a cast parameter:
 *
 *   'expires_at' => FormattedDateTime::class            // datetime (default)
 *   'valid_on'   => FormattedDateTime::class.':date'    // date only
 *
 * **datetime** — stores UTC, presents in the app-wide settings timezone as
 * `Y-m-d h:iA` (see self::timezone()). Timestamp columns (created/updated/
 * deleted/archived) are normalised straight to UTC; everything else round-trips
 * through the settings timezone.
 *
 * **date** — formats as `Y-m-d` and is *never* timezone-converted. A calendar
 * date has no time of day, so shifting it across zones would move it a whole
 * day: `valid_on = 2026-09-13` read as midnight and converted to a negative
 * offset renders as the 12th — the wrong day. The absence of a timezone call in
 * the date path is deliberate; do not "fix" it.
 */
class FormattedDateTime implements CastsAttributes
{
    /** Cast parameter: 'datetime' (default) or 'date'. */
    public const DATE_ONLY = 'date';

    public function __construct(private readonly string $precision = 'datetime') {}

    private function isDateOnly(): bool
    {
        return $this->precision === self::DATE_ONLY;
    }

    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $this->isDateOnly() ? self::getDate($value) : self::getTime($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if (! $value) {
            return null;
        }

        if ($this->isDateOnly()) {
            return self::setDate($value);
        }

        if (in_array($key, [$model->getCreatedAtColumn(), $model->getUpdatedAtColumn(), 'deleted_at', 'archived_at'], true)) {
            return Carbon::parse($value)->utc()->toDateTimeString();
        }

        return self::setTime($value);
    }

    /**
     * Present a stored calendar date as `Y-m-d`, with no timezone conversion.
     * Reading and writing are the same operation — a date is stored exactly as
     * it is displayed — but both exist so call sites read symmetrically.
     */
    public static function getDate(mixed $value, ?string $format = null): ?string
    {
        return $value ? Carbon::parse($value)->format($format ?? 'Y-m-d') : null;
    }

    /** Normalise a calendar date to `Y-m-d` for storage, with no conversion. */
    public static function setDate(mixed $value, ?string $format = null): ?string
    {
        return self::getDate($value, $format);
    }

    /** Present a stored (UTC) value in the settings timezone. */
    public static function getTime(mixed $value, ?string $format = null): ?string
    {
        return $value
            ? Carbon::parse($value)->timezone(self::timezone())->format($format ?? 'Y-m-d h:iA')
            : null;
    }

    /** Normalise a settings-timezone wall-clock value to a UTC string for storage. */
    public static function setTime(mixed $value, ?string $format = null): ?string
    {
        return Carbon::parse($value, self::timezone())->utc()->format($format ?? 'Y-m-d H:i:s');
    }

    /**
     * Normalise a settings-timezone wall-clock string to a UTC datetime string.
     *
     * Use this before handing a wall-clock date to something that writes it
     * without going through the cast — a raw query-builder insert, or a queue
     * payload a worker persists later — which would otherwise store the
     * wall-clock value verbatim as if it were already UTC.
     */
    public static function toUtc(mixed $value): ?string
    {
        return $value ? self::setTime($value) : null;
    }

    public static function startOfDayUtc(mixed $date): Carbon
    {
        return Carbon::parse($date, self::timezone())->startOfDay()->utc();
    }

    public static function endOfDayUtc(mixed $date): Carbon
    {
        return Carbon::parse($date, self::timezone())->endOfDay()->utc();
    }

    /**
     * The single app-wide display timezone: the `timezone` setting, falling back
     * to config('app.timezone') then UTC.
     *
     * There is deliberately no per-user timezone — every user of the system sees
     * every datetime in the one timezone chosen in the dashboard settings. This
     * resolves identically in web, API, console and queue contexts because it
     * never consults the authenticated user.
     */
    public static function timezone(): string
    {
        return getSettings('timezone') ?: (config('app.timezone') ?: 'UTC');
    }
}
