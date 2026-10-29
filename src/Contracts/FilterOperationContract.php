<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\Contracts;

use Dashworthy\ApiFilters\Data\FilterData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One operator a client can name in a filter's filter_operator. Every class in
 * api-filters.operations whose canHandle() accepts a filter checks its value
 * against rules(), then adds its condition, and a filter that none can handle
 * is rejected. The package calls handle() inside a where group of the
 * filter's own, joined to the rest of its set by the set's operator, and for a
 * relation field inside that relation's has() query with the column qualified
 * for the related table, so an operation only writes its own condition.
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
     * Add the filter's condition. The column is one the package resolved and
     * the value passed rules(); bind the value rather than writing it into SQL.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function handle(Builder $query, FilterData $filterData): Builder;
}
