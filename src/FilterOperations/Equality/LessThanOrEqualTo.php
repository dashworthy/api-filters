<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\FilterOperations\Equality;

use Dashworthy\ApiFilters\Contracts\DatabaseAdapterContract;
use Dashworthy\ApiFilters\Contracts\FilterOperationContract;
use Dashworthy\ApiFilters\Data\FilterData;
use Dashworthy\ApiFilters\Enums\FilterSetOperator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Matches rows whose column is less than or equal to the value.
 */
final class LessThanOrEqualTo implements FilterOperationContract
{
    public function handle(Builder $query, FilterData $filterData, FilterSetOperator $filterSetOperator, DatabaseAdapterContract $adapter): Builder
    {
        return $adapter->compare($query, $filterData->column, '<=', $filterData->value, $filterSetOperator->value);
    }
}
