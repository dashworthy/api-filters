<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters;

use Closure;
use Dashworthy\ApiFilters\Columns\ColumnResolver;
use Dashworthy\ApiFilters\Contracts\FilterOperationContract;
use Dashworthy\ApiFilters\Filtering\FilterApplier;
use Dashworthy\ApiFilters\Parsing\FilterSetParser;
use Dashworthy\ApiFilters\Parsing\SortParser;
use Dashworthy\ApiFilters\Relations\RelationAllowList;
use Dashworthy\ApiFilters\Sorting\SortApplier;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Applies a request's filter sets, sorts and result window to an Eloquent
 * query. Only visible columns of the query's model, and of the models reached
 * through relation paths the developer allows, can be filtered or sorted. Every
 * value is checked against the rules of the operation that applies it, and
 * each set is applied inside its own where group, so filters can narrow the
 * query but never widen the constraints applied before them.
 *
 * @template TModel of Model
 */
final class ApiFilters
{
    private readonly ColumnResolver $columns;

    private RelationAllowList $relations;

    /**
     * @param  Builder<TModel>  $query
     */
    private function __construct(private readonly Builder $query)
    {
        $this->columns = new ColumnResolver;
        $this->relations = new RelationAllowList($query->getModel(), []);
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
     * Allow clients to filter through these relation paths, written as the
     * model's relation method names joined by dots, such as lineItems.product.
     * A path also allows each shorter path it starts with. Every segment is
     * checked here, before any client input is read.
     *
     * @param  list<string>  $paths
     * @return $this
     *
     * @throws LogicException when a segment is not a relation method
     */
    public function allowedRelations(array $paths): self
    {
        $this->relations = new RelationAllowList($this->query->getModel(), $paths);

        return $this;
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
        $filterSets = (new FilterSetParser($this->columns, $this->relations, $applier))->parse($filterSets, $this->query->getModel());

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
     * Order the query by a request's sorts, in turn. Every sort is validated
     * before any is applied, so a rejected request leaves the query as it was.
     *
     * @return $this
     *
     * @throws ValidationException
     */
    public function sort(mixed $sorts): self
    {
        $applier = new SortApplier;

        foreach ((new SortParser($this->columns, $this->relations))->parse($sorts, $this->query->getModel()) as $sortData) {
            $applier->apply($this->query, $sortData);
        }

        return $this;
    }

    /**
     * Limit the query to the rows from position first, counted from zero, up
     * to but not including position last.
     *
     * @return $this
     *
     * @throws ValidationException when first is negative, last is not after first, or the window is over the limit
     */
    public function window(int $first, int $last): self
    {
        $rules = self::windowRules($first);

        Validator::make(['first' => $first, 'last' => $last], $rules)->validate();

        $this->query->offset($first)->limit($last - $first);

        return $this;
    }

    /**
     * The rules a result window's first and last must pass, shared with
     * FilterableRequest: first is a position, last comes after it, and the
     * window holds no more than api-filters.limits.window rows.
     *
     * @return array{first: list<mixed>, last: list<mixed>}
     */
    public static function windowRules(mixed $first): array
    {
        $limit = Config::integer('api-filters.limits.window');

        return [
            'first' => ['integer', 'min:0'],
            'last' => [
                'integer',
                'gt:first',
                function (string $attribute, mixed $last, Closure $fail) use ($first, $limit): void {
                    if (is_numeric($first) && is_numeric($last) && $last - $first > $limit) {
                        $fail(Lang::get('api-filters::validation.window_too_large', ['max' => (string) $limit]));
                    }
                },
            ],
        ];
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
