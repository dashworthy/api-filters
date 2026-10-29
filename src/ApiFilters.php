<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters;

use Dashworthy\ApiFilters\Columns\ColumnResolver;
use Dashworthy\ApiFilters\Contracts\FilterOperationContract;
use Dashworthy\ApiFilters\Filtering\FilterApplier;
use Dashworthy\ApiFilters\Parsing\FilterSetParser;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Applies a request's filter sets to an Eloquent query. Only visible columns of
 * the query's model can be filtered, every value is checked against its
 * operator, and each set is applied inside its own where group, so filters can
 * narrow the query but never widen the constraints applied before them.
 *
 * @template TModel of Model
 */
final class ApiFilters
{
    private readonly ColumnResolver $columns;

    /**
     * @param  Builder<TModel>  $query
     */
    private function __construct(private readonly Builder $query)
    {
        $this->columns = new ColumnResolver;
    }

    /**
     * Wrap a query. The engine changes the builder it is given.
     *
     * @template TQueryModel of Model
     *
     * @param  Builder<TQueryModel>  $query
     * @return self<TQueryModel>
     */
    public static function for(Builder $query): self
    {
        return new self($query);
    }

    /**
     * Apply a request's filter_sets. Every set is validated before any is
     * applied, so a rejected request leaves the query as it was.
     *
     * @return $this
     *
     * @throws ValidationException
     */
    public function filter(mixed $filterSets): self
    {
        $applier = new FilterApplier($this->operations());
        $filterSets = (new FilterSetParser($this->columns, $applier))->parse($filterSets, $this->query->getModel());

        foreach ($filterSets as $filterSet) {
            $this->query->where(function (Builder $group) use ($applier, $filterSet): void {
                foreach ($filterSet->filters as $filter) {
                    $applier->apply($group, $filter, $filterSet->operator);
                }
            });
        }

        return $this;
    }

    /**
     * @return Builder<TModel>
     */
    public function query(): Builder
    {
        return $this->query;
    }

    /**
     * The operations api-filters.operations lists, in order. An entry is an
     * operation, or a class the container resolves to one.
     *
     * @return list<FilterOperationContract>
     */
    private function operations(): array
    {
        return array_map(function (mixed $operation): FilterOperationContract {
            $operation = is_string($operation) ? Container::getInstance()->make($operation) : $operation;

            if (! $operation instanceof FilterOperationContract) {
                throw new LogicException('Every entry in api-filters.operations must implement '.FilterOperationContract::class.'.');
            }

            return $operation;
        }, array_values(Config::array('api-filters.operations')));
    }
}
