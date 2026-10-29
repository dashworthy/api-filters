<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\FilterOperations\Sets;

use Dashworthy\ApiFilters\Contracts\DatabaseAdapterContract;
use Dashworthy\ApiFilters\Contracts\FilterOperationContract;
use Dashworthy\ApiFilters\Data\FilterData;
use Dashworthy\ApiFilters\Enums\FilterSetOperator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Matches rows whose column equals none of the listed values. NULL rows never
 * match. A null in the list is left out of the NOT IN, where it would match
 * nothing at all, and becomes an IS NOT NULL in the same nested group.
 */
final class NotIn implements FilterOperationContract
{
    public function handle(Builder $query, FilterData $filterData, FilterSetOperator $filterSetOperator, DatabaseAdapterContract $adapter): Builder
    {
        $listed = array_values(array_filter($filterData->values, fn (mixed $value): bool => $value !== null));

        if (count($listed) === count($filterData->values)) {
            return $adapter->whereIn($query, $filterData->column, $listed, $filterSetOperator->value, not: true);
        }

        return $query->where(
            fn (Builder $nested) => $adapter->whereIn($nested, $filterData->column, $listed, 'and', not: true)->whereNotNull($filterData->column),
            boolean: $filterSetOperator->value,
        );
    }
}
