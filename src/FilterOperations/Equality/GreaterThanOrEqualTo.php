<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\FilterOperations\Equality;

use Dashworthy\ApiFilters\Contracts\DatabaseAdapterContract;
use Dashworthy\ApiFilters\Contracts\FilterOperationContract;
use Dashworthy\ApiFilters\Data\FilterData;
use Dashworthy\ApiFilters\Enums\FilterOperator;
use Dashworthy\ApiFilters\Enums\FilterSetOperator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/**
 * Matches rows whose column is greater than or equal to the value.
 */
final class GreaterThanOrEqualTo implements FilterOperationContract
{
    public function canHandle(FilterData $filterData): bool
    {
        return $filterData->operator === FilterOperator::Gte->value;
    }

    /**
     * One value.
     */
    public function rules(): array
    {
        return [Rule::anyOf(['string', 'numeric', 'boolean'])];
    }

    public function handle(Builder $query, FilterData $filterData, FilterSetOperator $filterSetOperator, DatabaseAdapterContract $adapter): Builder
    {
        $value = $filterData->scalarValue();

        return $adapter->compare($query, $filterData->column, '>=', $value, $filterSetOperator->value);
    }
}
