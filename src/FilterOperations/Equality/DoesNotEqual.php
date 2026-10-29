<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\FilterOperations\Equality;

use Dashworthy\ApiFilters\Contracts\DatabaseAdapterContract;
use Dashworthy\ApiFilters\Contracts\FilterOperationContract;
use Dashworthy\ApiFilters\Data\FilterData;
use Dashworthy\ApiFilters\Enums\FilterSetOperator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Matches rows whose column differs from the value, which leaves out NULL
 * rows, or is not NULL when the value is null.
 */
final class DoesNotEqual implements FilterOperationContract
{
    public function handle(Builder $query, FilterData $filterData, FilterSetOperator $filterSetOperator, DatabaseAdapterContract $adapter): Builder
    {
        if ($filterData->value === null) {
            return $query->whereNotNull($filterData->column, $filterSetOperator->value);
        }

        return $adapter->compare($query, $filterData->column, '!=', $filterData->value, $filterSetOperator->value);
    }
}
