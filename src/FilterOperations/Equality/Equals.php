<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\FilterOperations\Equality;

use Dashworthy\ApiFilters\Contracts\FilterOperationContract;
use Dashworthy\ApiFilters\Data\FilterData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/**
 * Matches rows whose column equals the value, or is NULL when the value is null.
 */
final class Equals implements FilterOperationContract
{
    public function canHandle(FilterData $filterData): bool
    {
        return $filterData->operator === 'equals';
    }

    public function rules(): array
    {
        return ['nullable', Rule::anyOf(['string', 'numeric', 'boolean'])];
    }

    public function handle(Builder $query, FilterData $filterData): Builder
    {
        $value = $filterData->scalarValue();

        return $value === null
            ? $query->whereNull($filterData->column)
            : $query->where($filterData->column, '=', $value);
    }
}
