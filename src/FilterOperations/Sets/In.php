<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\FilterOperations\Sets;

use Dashworthy\ApiFilters\Contracts\DatabaseAdapterContract;
use Dashworthy\ApiFilters\Contracts\FilterOperationContract;
use Dashworthy\ApiFilters\Data\FilterData;
use Dashworthy\ApiFilters\Enums\FilterSetOperator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Matches rows whose column equals any listed value. A null in the list also
 * matches NULL rows, inside a nested group so its OR stays within the filter.
 */
final class In implements FilterOperationContract
{
    public function handle(Builder $query, FilterData $filterData, FilterSetOperator $filterSetOperator, DatabaseAdapterContract $adapter): Builder
    {
        $listed = array_values(array_filter($filterData->values, fn (mixed $value): bool => $value !== null));

        if (count($listed) === count($filterData->values)) {
            return $adapter->whereIn($query, $filterData->column, $listed, $filterSetOperator->value);
        }

        return $query->where(
            fn (Builder $nested) => $adapter->whereIn($nested, $filterData->column, $listed, 'and')->orWhereNull($filterData->column),
            boolean: $filterSetOperator->value,
        );
    }
}
