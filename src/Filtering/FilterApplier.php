<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\Filtering;

use Dashworthy\ApiFilters\Contracts\DatabaseAdapterContract;
use Dashworthy\ApiFilters\Contracts\FilterOperationContract;
use Dashworthy\ApiFilters\Data\FilterData;
use Dashworthy\ApiFilters\Enums\FilterOperator;
use Dashworthy\ApiFilters\Enums\FilterSetOperator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Hands each filter to the first operation that can handle it, with the
 * adapter for the query's database. A filter through a relation runs inside
 * has(), joined by the set's operator, so it matches rows with at least one
 * related row that satisfies it, and its column is qualified there against the
 * related table. doesntHave names the relation itself, so it is handed the
 * set's group.
 */
final class FilterApplier
{
    /**
     * @param  list<FilterOperationContract>  $operations  in the order api-filters.operations lists them
     */
    public function __construct(
        private readonly DatabaseAdapterContract $adapter,
        private readonly array $operations,
    ) {}

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $group
     *
     * @throws LogicException when no operation can handle the filter
     */
    public function apply(Builder $group, FilterData $filterData, FilterSetOperator $filterSetOperator): void
    {
        $operation = $this->operationFor($filterData)
            ?? throw new LogicException("No api-filters operation can handle the [{$filterData->operator}] operator.");

        if ($filterData->relation === null || $filterData->operator === FilterOperator::DoesntHave->value) {
            $operation->handle($group, $filterData, $filterSetOperator, $this->adapter);

            return;
        }

        $group->has(
            $filterData->relation,
            '>=',
            1,
            $filterSetOperator->value,
            fn (Builder $related) => $operation->handle(
                $related,
                $filterData->withColumn($related->qualifyColumn($filterData->column)),
                FilterSetOperator::And,
                $this->adapter,
            ),
        );
    }

    /**
     * The first operation that can handle the filter, or null when none can.
     * The parser checks each value against the rules of the operation this
     * returns, so the operation that checked a value is the one that applies it.
     */
    public function operationFor(FilterData $filterData): ?FilterOperationContract
    {
        foreach ($this->operations as $operation) {
            if ($operation->canHandle($filterData)) {
                return $operation;
            }
        }

        return null;
    }
}
