<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\FilterOperations\Sets;

use Dashworthy\ApiFilters\Contracts\FilterOperationContract;
use Dashworthy\ApiFilters\Data\FilterData;
use Dashworthy\ApiFilters\Enums\FilterOperator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Matches rows whose column equals none of the listed values. NULL rows never
 * match. A null in the list is left out of the NOT IN, where it would match
 * nothing at all, and becomes an IS NOT NULL.
 */
final class NotIn implements FilterOperationContract
{
    public function canHandle(FilterData $filterData): bool
    {
        return $filterData->operator === FilterOperator::NotIn->value;
    }

    /**
     * The same list as in.
     */
    public function rules(): array
    {
        return (new In)->rules();
    }

    public function handle(Builder $query, FilterData $filterData): Builder
    {
        $values = $filterData->listValue();
        $listed = array_values(array_filter($values, fn (mixed $value): bool => $value !== null));

        $query->whereNotIn($filterData->column, $listed);

        if (count($listed) < count($values)) {
            $query->whereNotNull($filterData->column);
        }

        return $query;
    }
}
