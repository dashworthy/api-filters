<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\FilterOperations\Equality;

use Dashworthy\ApiFilters\Contracts\FilterOperationContract;
use Dashworthy\ApiFilters\Data\FilterData;
use Dashworthy\ApiFilters\Enums\FilterOperator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/**
 * Matches rows whose column differs from the value, which leaves out NULL
 * rows, or is not NULL when the value is null.
 */
final class DoesNotEqual implements FilterOperationContract
{
    public function canHandle(FilterData $filterData): bool
    {
        return $filterData->operator === FilterOperator::DoesNotEquals->value;
    }

    /**
     * One value, or null for rows with no value.
     */
    public function rules(): array
    {
        return ['nullable', Rule::anyOf(['string', 'numeric', 'boolean'])];
    }

    public function handle(Builder $query, FilterData $filterData): Builder
    {
        $value = $filterData->scalarValue();

        return $value === null
            ? $query->whereNotNull($filterData->column)
            : $query->where($filterData->column, '!=', $value);
    }
}
