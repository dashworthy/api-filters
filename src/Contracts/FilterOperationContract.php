<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\Contracts;

use Dashworthy\ApiFilters\Data\FilterData;
use Dashworthy\ApiFilters\Enums\FilterSetOperator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One operator a client can name in a filter's filter_operator. The classes in
 * api-filters.operations are asked in order: the first whose canHandle()
 * accepts a filter checks its value against rules() and adds its condition to
 * the filter set's group, and a filter that none can handle is rejected. The
 * adapter writes any SQL that differs between databases.
 */
interface FilterOperationContract
{
    /**
     * Whether this operation applies the filter. It is asked before the value
     * is checked and again when the filter is applied, so it must give the
     * same answer both times.
     */
    public function canHandle(FilterData $filterData): bool;

    /**
     * The Laravel validation rules the filter's value must pass. Every filter
     * is checked before any is applied.
     *
     * @return list<mixed>
     */
    public function rules(): array;

    /**
     * Add the filter's condition, joined by the set's operator. The column is
     * one the package resolved and the value passed rules(); bind the value
     * rather than writing it into SQL.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function handle(Builder $query, FilterData $filterData, FilterSetOperator $filterSetOperator, DatabaseAdapterContract $adapter): Builder;
}
