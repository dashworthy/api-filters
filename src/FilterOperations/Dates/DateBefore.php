<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\FilterOperations\Dates;

use Dashworthy\ApiFilters\Contracts\DatabaseAdapterContract;
use Dashworthy\ApiFilters\Contracts\FilterOperationContract;
use Dashworthy\ApiFilters\Data\FilterData;
use Dashworthy\ApiFilters\Enums\FilterOperator;
use Dashworthy\ApiFilters\Enums\FilterSetOperator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Matches rows whose column falls on an earlier date.
 */
final class DateBefore implements FilterOperationContract
{
    public function canHandle(FilterData $filterData): bool
    {
        return $filterData->operator === FilterOperator::DateBefore->value;
    }

    /**
     * A YYYY-MM-DD calendar date.
     */
    public function rules(): array
    {
        return ['required', 'date_format:Y-m-d'];
    }

    public function handle(Builder $query, FilterData $filterData, FilterSetOperator $filterSetOperator, DatabaseAdapterContract $adapter): Builder
    {
        return $adapter->whereDate($query, $filterData->column, '<', $filterData->stringValue(), $filterSetOperator->value);
    }
}
