<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\FilterOperations\Dates;

use Dashworthy\ApiFilters\Contracts\DatabaseAdapterContract;
use Dashworthy\ApiFilters\Contracts\FilterOperationContract;
use Dashworthy\ApiFilters\Data\FilterData;
use Dashworthy\ApiFilters\Enums\FilterSetOperator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Matches rows whose column falls on any other date.
 */
final class DateIsNot implements FilterOperationContract
{
    public function handle(Builder $query, FilterData $filterData, FilterSetOperator $filterSetOperator, DatabaseAdapterContract $adapter): Builder
    {
        return $adapter->whereDate($query, $filterData->column, '!=', (string) $filterData->value, $filterSetOperator->value);
    }
}
