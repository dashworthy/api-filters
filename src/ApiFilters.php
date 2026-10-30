<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters;

use Dashworthy\ApiFilters\Attributes\Filterable;
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
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use ReflectionClass;

/**
 * Applies a validated FilterableRequest's filter sets and sorts to an Eloquent
 * query. Each field is checked against the query's model first: only the
 * columns and relation paths its #[Filterable] lists, and the columns the
 * related models list, can be filtered or sorted. Each set is applied inside
 * its own where group, so filters can narrow the query but never widen the
 * constraints applied before them.
 *
 * @template TModel of Model
 */
final class ApiFilters
{
    private readonly RelationAllowList $relations;

    /**
     * @param  Builder<TModel>  $query
     *
     * @throws LogicException when a listed relation path names no relation method
     */
    private function __construct(private readonly Builder $query)
    {
        $model = $query->getModel();

        $this->relations = new RelationAllowList($model, self::filterable($model)->relations ?? []);
    }

    /**
     * Wrap a query. The engine changes the builder it is given.
     *
     * @template TQueryModel of Model
     *
     * @param  Builder<TQueryModel>  $query
     * @return self<TQueryModel>
     *
     * @throws LogicException when a listed relation path names no relation method
     */
    public static function for(Builder $query): self
    {
        return new self($query);
    }

    /**
     * Apply the request's filter sets, then its sorts. Every field is checked
     * before any is applied, so a rejected request leaves the query as it was.
     *
     * @return $this
     *
     * @throws ValidationException when a field names nothing the model exposes
     */
    public function apply(FilterableRequest $request): self
    {
        $filterSets = $this->resolveFilterSets($request->filterSets());
        $sorts = $this->resolveSorts($request->sorts());
        $applier = FilterApplier::fromConfig();

        foreach ($filterSets as $filterSet) {
            $this->query->where(function (Builder $group) use ($applier, $filterSet): void {
                foreach ($filterSet->filters as $filter) {
                    $applier->apply($group, $filter, $filterSet->operator);
                }
            });
        }

        foreach ($sorts as $sortData) {
            (new SortApplier)->apply($this->query, $sortData);
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
     * query's model. A dotted field is a relation path the model lists and a
     * column of the model it reaches, which the applier qualifies inside the
     * relation's subquery. A path through a morph-to reaches no single model,
     * so no column resolves through it. For doesntHave the whole field is the
     * path. A client's path is only looked up, never handed to the model: what
     * comes back is the developer's spelling from #[Filterable].
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
            $column = self::column($this->query->getModel(), $field);

            return $column === null ? null : ['column' => $this->query->qualifyColumn($column), 'relation' => null];
        }

        $relation = $this->relations->find(Str::beforeLast($field, '.'));
        $column = $relation === null || $relation['model'] === null ? null : self::column($relation['model'], Str::afterLast($field, '.'));

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
     * #[Filterable].
     *
     * @return array{column: string, relation: string|null}|null
     */
    private function sortTarget(string $field): ?array
    {
        if (! str_contains($field, '.')) {
            $column = self::column($this->query->getModel(), $field);

            return $column === null ? null : ['column' => $this->query->qualifyColumn($column), 'relation' => null];
        }

        $relation = $this->relations->find(Str::beforeLast($field, '.'));

        if ($relation === null || ! $relation['toOne'] || $relation['model'] === null) {
            return null;
        }

        $column = self::column($relation['model'], Str::afterLast($field, '.'));

        return $column === null ? null : ['column' => $column, 'relation' => $relation['path']];
    }

    /**
     * The model's spelling of the column a client's field names, matched in
     * any letter case, or null when its #[Filterable] does not list it or the
     * model hides it. The table is never read.
     */
    private static function column(Model $model, string $field): ?string
    {
        $columns = self::filterable($model)->columns ?? [];
        $column = array_combine(array_map(strtolower(...), $columns), $columns)[strtolower($field)] ?? null;
        $visible = array_map(strtolower(...), $model->getVisible());

        return $column === null
            || in_array(strtolower($column), array_map(strtolower(...), $model->getHidden()), true)
            || ($visible !== [] && ! in_array(strtolower($column), $visible, true)) ? null : $column;
    }

    /**
     * The model's #[Filterable], found as Laravel finds #[Hidden]: on the
     * class, then its traits, then each parent class and its traits.
     */
    private static function filterable(Model $model): ?Filterable
    {
        for ($class = new ReflectionClass($model); $class !== false; $class = $class->getParentClass()) {
            foreach ([$class, ...$class->getTraits()] as $declaring) {
                $attributes = $declaring->getAttributes(Filterable::class);

                if ($attributes !== []) {
                    return $attributes[0]->newInstance();
                }
            }
        }

        return null;
    }

    private static function message(string $key): string
    {
        $message = Lang::get("api-filters::validation.{$key}");

        return is_string($message) ? $message : $key;
    }
}
