<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\FilterOperations\Text;

use Dashworthy\ApiFilters\Contracts\FilterOperationContract;
use Dashworthy\ApiFilters\Data\FilterData;
use Dashworthy\ApiFilters\Enums\FilterOperator;
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

    public function handle(Builder $query, FilterData $filterData): Builder
    {
        return $query->whereLike($filterData->column, '%'.$filterData->scalarValue().'%');
    }
}
