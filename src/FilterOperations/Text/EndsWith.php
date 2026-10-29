<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\FilterOperations\Text;

use Dashworthy\ApiFilters\Contracts\DatabaseAdapterContract;
use Dashworthy\ApiFilters\Contracts\FilterOperationContract;
use Dashworthy\ApiFilters\Data\FilterData;
use Dashworthy\ApiFilters\Enums\FilterSetOperator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Matches rows whose column ends with the value.
 * A % or _ in the value is a LIKE wildcard.
 */
final class EndsWith implements FilterOperationContract
{
    public function handle(Builder $query, FilterData $filterData, FilterSetOperator $filterSetOperator, DatabaseAdapterContract $adapter): Builder
    {
        return $adapter->whereLike($query, $filterData->column, '%'.$filterData->value, $filterSetOperator->value);
    }
}
