<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes the conditions whose SQL differs between databases, so a filter gives
 * the same results on every database it supports. api-filters.adapters maps a
 * connection's driver name to the adapter that writes its SQL.
 */
interface DatabaseAdapterContract
{
    /**
     * Compare the column with a value using =, !=, <, <=, > or >=.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  '='|'!='|'<'|'<='|'>'|'>='  $operator
     * @return Builder<TModel>
     */
    public function compare(Builder $query, string $column, string $operator, string|int|float|bool|null $value, string $boolean): Builder;

    /**
     * Match the column against a list of values with IN, or NOT IN.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<scalar>  $values
     * @return Builder<TModel>
     */
    public function whereIn(Builder $query, string $column, array $values, string $boolean, bool $not = false): Builder;

    /**
     * Match the column against a LIKE pattern, or NOT LIKE, in any letter
     * case. Every % and _ in the pattern is a wildcard.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function whereLike(Builder $query, string $column, string $pattern, string $boolean, bool $not = false): Builder;

    /**
     * Compare the calendar date of the column with a YYYY-MM-DD date.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  '='|'!='|'<'|'>'  $operator
     * @return Builder<TModel>
     */
    public function whereDate(Builder $query, string $column, string $operator, string $date, string $boolean): Builder;
}
