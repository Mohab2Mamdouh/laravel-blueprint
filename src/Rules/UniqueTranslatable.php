<?php

declare(strict_types=1);

namespace BlueprintPhp\Laravel\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Case-insensitive uniqueness check for a single locale of a Spatie-translatable
 * JSON column (e.g. `display_name->en`). MySQL compares JSON-extracted values with
 * the binary collation, so the native Rule::unique('table', 'display_name->en')
 * treats "Admin" and "admin" as different. This rule normalises both sides with
 * LOWER().
 */
class UniqueTranslatable implements ValidationRule
{
    public function __construct(
        private readonly string $table,
        private readonly string $column,
        private readonly string $locale,
        private readonly int|string|null $ignoreId = null,
        private readonly string $idColumn = 'id',
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $path = "$.\"{$this->locale}\"";

        $query = DB::table($this->table)
            ->whereRaw(
                "LOWER(JSON_UNQUOTE(JSON_EXTRACT(`{$this->column}`, ?))) = ?",
                [$path, Str::lower($value)],
            );

        if ($this->ignoreId !== null) {
            $query->where($this->idColumn, '!=', $this->ignoreId);
        }

        if ($query->exists()) {
            $fail('validation.unique')->translate();
        }
    }
}
