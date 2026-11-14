<?php

namespace BlueprintPhp\Laravel\Enums;

use BlueprintPhp\Laravel\Traits\Enumable;

enum QueryReturnType: string
{
    use Enumable;

    case GET = 'get';
    case FIRST = 'first';
    case FIRST_OR_FAIL = 'firstOrFail';
    case PAGINATE = 'paginate';
    case CURSOR_PAGINATE = 'cursor_paginate';
    case QUERY = 'query';
    case PLUCK = 'pluck';
    case COUNT = 'count';
    case EXISTS = 'exists';

    /**
     * Check if the return type requires pagination.
     */
    public function requiresPagination(): bool
    {
        return in_array($this, [self::PAGINATE, self::CURSOR_PAGINATE], true);
    }

    /**
     * Check if the return type returns a single record.
     */
    public function returnsSingle(): bool
    {
        return in_array($this, [self::FIRST, self::FIRST_OR_FAIL], true);
    }

    /**
     * Check if the return type returns a collection.
     */
    public function returnsCollection(): bool
    {
        return $this === self::GET;
    }

    /**
     * Check if the return type returns a query builder.
     */
    public function returnsQuery(): bool
    {
        return $this === self::QUERY;
    }
}
