<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\Adapters;

use Dashworthy\ApiFilters\Contracts\DatabaseAdapterContract;
use Illuminate\Database\Eloquent\Builder;

/**
 * Writes MariaDB's SQL through Laravel's own builder methods, with
 * case-insensitive pattern matching.
 */
final class MariaDbAdapter implements DatabaseAdapterContract
{
    public function compare(Builder $query, string $column, string $operator, string|int|float|bool|null $value, string $boolean): Builder
    {
        return $query->where($column, $operator, $value, $boolean);
    }

    public function whereIn(Builder $query, string $column, array $values, string $boolean, bool $not = false): Builder
    {
        return $query->whereIn($column, $values, $boolean, $not);
    }

    public function whereLike(Builder $query, string $column, string $pattern, string $boolean, bool $not = false): Builder
    {
        return $query->whereLike($column, $pattern, caseSensitive: false, boolean: $boolean, not: $not);
    }

    public function whereDate(Builder $query, string $column, string $operator, string $date, string $boolean): Builder
    {
        return $query->whereDate($column, $operator, $date, $boolean);
    }
}
