<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\Filtering;

use Dashworthy\ApiFilters\Contracts\FilterOperationContract;
use Dashworthy\ApiFilters\Data\FilterData;
use Dashworthy\ApiFilters\Enums\FilterSetOperator;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use LogicException;

/**
 * Each filter gets a where group of its own, so an OR inside an operation
 * stays within the filter. doesntHave names a relation, so it is not wrapped
 * in that relation's has() query.
 */
final class FilterApplier
{
    /**
     * @param  list<FilterOperationContract>  $operations  in the order api-filters.operations lists them
     */
    public function __construct(private readonly array $operations) {}

    /**
     * An applier for the operations api-filters.operations lists, in order. An
     * entry is an operation, or a class the container resolves to one.
     *
     * @throws LogicException when an entry is not an operation
     */
    public static function fromConfig(): self
    {
        return new self(array_map(function (mixed $operation): FilterOperationContract {
            $operation = is_string($operation) ? Container::getInstance()->make($operation) : $operation;

            if (! $operation instanceof FilterOperationContract) {
                throw new LogicException('Every entry in api-filters.operations must implement '.FilterOperationContract::class.'.');
            }

            return $operation;
        }, array_values(Config::array('api-filters.operations'))));
    }

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
            if ($filterData->relation === null || $filterData->operator === 'doesntHave') {
                $this->handle($filter, $operations, $filterData);

                return;
            }

            $filter->has($filterData->relation, '>=', 1, 'and', function (Builder $related) use ($operations, $filterData): void {
                $this->handle($related, $operations, $filterData->withColumn($related->qualifyColumn($filterData->column)));
            });
        }, boolean: $filterSetOperator->value);
    }

    /**
     * Every operation that can handle the filter, in order. FilterableRequest
     * checks each value against all of their rules, so the operations that
     * checked a value are the ones that apply it.
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

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<FilterOperationContract>  $operations
     */
    private function handle(Builder $query, array $operations, FilterData $filterData): void
    {
        foreach ($operations as $operation) {
            $operation->handle($query, $filterData);
        }
    }
}
