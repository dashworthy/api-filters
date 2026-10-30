<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters;

use Closure;
use Dashworthy\ApiFilters\Columns\ColumnResolver;
use Dashworthy\ApiFilters\Data\FilterData;
use Dashworthy\ApiFilters\Data\FilterSetData;
use Dashworthy\ApiFilters\Data\SortData;
use Dashworthy\ApiFilters\Enums\FilterOperator;
use Dashworthy\ApiFilters\Enums\FilterSetOperator;
use Dashworthy\ApiFilters\Enums\SortOperator;
use Dashworthy\ApiFilters\Filtering\FilterApplier;
use Dashworthy\ApiFilters\Http\FilterableRequest;
use Dashworthy\ApiFilters\Relations\RelationAllowList;
use Dashworthy\ApiFilters\Sorting\SortApplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Applies a request's filter sets, sorts and result window to an Eloquent
 * query. Input is checked as FilterableRequest checks it, then each field
 * against the query's model: only the columns it lists with #[Filterable],
 * and those the models reached through relation paths the developer allows
 * list, can be filtered or sorted. Each set is applied inside its own where
 * group, so filters can narrow the query but never widen the constraints
 * applied before them.
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
     * Apply a request's filter_sets. They are checked as FilterableRequest
     * checks them, then each field against the query's model, before any is
     * applied, so a rejected request leaves the query as it was.
     *
     * @return $this
     *
     * @throws ValidationException
     */
    public function filter(mixed $filterSets): self
    {
        $applier = FilterApplier::fromConfig();

        foreach ($this->resolveFilterSets(FilterableRequest::validateFilterSets($filterSets)) as $filterSet) {
            $this->query->where(function (Builder $group) use ($applier, $filterSet): void {
                foreach ($filterSet->filters as $filter) {
                    $applier->apply($group, $filter, $filterSet->operator);
                }
            });
        }

        return $this;
    }

    /**
     * Order the query by a request's sorts, in turn. They are checked as
     * FilterableRequest checks them, then each field against the query's
     * model, before any is applied, so a rejected request leaves the query as
     * it was.
     *
     * @return $this
     *
     * @throws ValidationException
     */
    public function sort(mixed $sorts): self
    {
        $applier = new SortApplier;

        foreach ($this->resolveSorts(FilterableRequest::validateSorts($sorts)) as $sortData) {
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
     * The filter sets with each field resolved against the query's model.
     *
     * @param  list<array{filter_set_operator: string, filters: list<array{field: string, filter_operator: string, value: mixed}>}>  $filterSets
     * @return list<FilterSetData>
     *
     * @throws ValidationException when a field names nothing filterable
     */
    private function resolveFilterSets(array $filterSets): array
    {
        $errors = [];
        $resolved = [];

        foreach ($filterSets as $setIndex => $set) {
            $filters = [];

            foreach ($set['filters'] as $filterIndex => $filter) {
                $target = $this->filterTarget($filter['field'], $filter['filter_operator']);

                if ($target === null) {
                    $errors["filter_sets.{$setIndex}.filters.{$filterIndex}.field"] = [self::message('not_filterable')];

                    continue;
                }

                $filters[] = new FilterData($target['column'], $filter['filter_operator'], $filter['value'], $target['relation']);
            }

            $resolved[] = new FilterSetData(FilterSetOperator::from($set['filter_set_operator']), $filters);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $resolved;
    }

    /**
     * The column and relation path a filter's field names, or null when it
     * names nothing filterable. A plain field is a qualified column of the
     * query's model. A dotted field is an allow-listed relation path and a
     * column of the model it reaches, which the applier qualifies inside the
     * relation's subquery. A path through a morph-to reaches no single model,
     * so no column resolves through it. For doesntHave the whole field is the
     * path. A client's path is only looked up, never handed to the model: what
     * comes back is the developer's spelling from the allow-list.
     *
     * @return array{column: string, relation: string|null}|null
     */
    private function filterTarget(string $field, string $operator): ?array
    {
        if ($operator === FilterOperator::DoesntHave->value) {
            $relation = $this->relations->find($field);

            return $relation === null ? null : ['column' => '', 'relation' => $relation['path']];
        }

        if (! str_contains($field, '.')) {
            $column = $this->columns->resolve($this->query->getModel(), $field);

            return $column === null ? null : ['column' => $column, 'relation' => null];
        }

        $relation = $this->relations->find(Str::beforeLast($field, '.'));
        $column = $relation === null || $relation['model'] === null ? null : $this->columns->column($relation['model'], Str::afterLast($field, '.'));

        return $relation === null || $column === null ? null : ['column' => $column, 'relation' => $relation['path']];
    }

    /**
     * The sorts with each field resolved against the query's model.
     *
     * @param  list<array{field: string, sort_operator: string}>  $sorts
     * @return list<SortData>
     *
     * @throws ValidationException when a field names nothing sortable
     */
    private function resolveSorts(array $sorts): array
    {
        $errors = [];
        $resolved = [];

        foreach ($sorts as $index => $sort) {
            $target = $this->sortTarget($sort['field']);

            if ($target === null) {
                $errors["sorts.{$index}.field"] = [self::message('not_sortable')];

                continue;
            }

            $resolved[] = new SortData($target['column'], SortOperator::from($sort['sort_operator']), $target['relation']);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $resolved;
    }

    /**
     * The column and to-one relation path a sort's field names, or null when
     * it names nothing sortable. A client's path is only looked up, never
     * handed to the model: what comes back is the developer's spelling from
     * the allow-list.
     *
     * @return array{column: string, relation: string|null}|null
     */
    private function sortTarget(string $field): ?array
    {
        if (! str_contains($field, '.')) {
            $column = $this->columns->resolve($this->query->getModel(), $field);

            return $column === null ? null : ['column' => $column, 'relation' => null];
        }

        $relation = $this->relations->find(Str::beforeLast($field, '.'));

        if ($relation === null || ! $relation['toOne'] || $relation['model'] === null) {
            return null;
        }

        $column = $this->columns->column($relation['model'], Str::afterLast($field, '.'));

        return $column === null ? null : ['column' => $column, 'relation' => $relation['path']];
    }

    private static function message(string $key): string
    {
        $message = Lang::get("api-filters::validation.{$key}");

        return is_string($message) ? $message : $key;
    }
}
