<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\FilterOperations\Text;

use Dashworthy\ApiFilters\Contracts\DatabaseAdapterContract;
use Dashworthy\ApiFilters\Contracts\FilterOperationContract;
use Dashworthy\ApiFilters\Data\FilterData;
use Dashworthy\ApiFilters\Enums\FilterOperator;
use Dashworthy\ApiFilters\Enums\FilterSetOperator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/**
 * Matches rows whose column contains the value.
 * A % or _ in the value is a LIKE wildcard.
 */
final class Contains implements FilterOperationContract
{
    public function canHandle(FilterData $filterData): bool
    {
        return $filterData->operator === FilterOperator::Contains->value;
    }

    /**
     * A string or a number.
     */
    public function rules(): array
    {
        return [Rule::anyOf(['string', 'numeric'])];
    }

    public function handle(Builder $query, FilterData $filterData, FilterSetOperator $filterSetOperator, DatabaseAdapterContract $adapter): Builder
    {
        $value = $filterData->scalarValue();

        return $adapter->whereLike($query, $filterData->column, '%'.$value.'%', $filterSetOperator->value);
    }
}
