<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\Filtering;

use Dashworthy\ApiFilters\Contracts\FilterOperationContract;
use Dashworthy\ApiFilters\Data\FilterData;
use Dashworthy\ApiFilters\Enums\FilterSetOperator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Hands each filter to every operation that can handle it, inside a where
 * group of the filter's own joined to the rest of its set by the set's
 * operator, so an operation only writes its own condition and even an OR in it
 * stays within the filter.
 */
final class FilterApplier
{
    /**
     * @param  list<FilterOperationContract>  $operations  in the order api-filters.operations lists them
     */
    public function __construct(private readonly array $operations) {}

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $group
     *
     * @throws LogicException when no operation can handle the filter
     */
    public function apply(Builder $group, FilterData $filterData, FilterSetOperator $filterSetOperator): void
    {
        $operations = $this->operationsFor($filterData);

        if ($operations === []) {
            throw new LogicException("No api-filters operation can handle the [{$filterData->operator}] operator.");
        }

        $group->where(function (Builder $filter) use ($operations, $filterData): void {
            foreach ($operations as $operation) {
                $operation->handle($filter, $filterData);
            }
        }, boolean: $filterSetOperator->value);
    }

    /**
     * Every operation that can handle the filter, in order. The parser checks
     * each value against all of their rules, so the operations that checked a
     * value are the ones that apply it.
     *
     * @return list<FilterOperationContract>
     */
    public function operationsFor(FilterData $filterData): array
    {
        return array_values(array_filter(
            $this->operations,
            fn (FilterOperationContract $operation): bool => $operation->canHandle($filterData),
        ));
    }
}
