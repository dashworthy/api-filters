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
 * Matches rows whose column does not contain the value, NULL rows included,
 * inside a nested group so its OR stays within the filter. A % or _ in the
 * value is a LIKE wildcard.
 */
final class DoesNotContain implements FilterOperationContract
{
    public function canHandle(FilterData $filterData): bool
    {
        return $filterData->operator === FilterOperator::DoesNotContain->value;
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
        $pattern = '%'.$value.'%';

        return $query->where(
            fn (Builder $nested) => $adapter->whereLike($nested, $filterData->column, $pattern, 'and', not: true)->orWhereNull($filterData->column),
            boolean: $filterSetOperator->value,
        );
    }
}
