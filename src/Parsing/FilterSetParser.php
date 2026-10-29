<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\Parsing;

use Closure;
use Dashworthy\ApiFilters\Columns\ColumnResolver;
use Dashworthy\ApiFilters\Contracts\FilterOperationContract;
use Dashworthy\ApiFilters\Contracts\NormalizerContract;
use Dashworthy\ApiFilters\Data\FilterData;
use Dashworthy\ApiFilters\Data\FilterSetData;
use Dashworthy\ApiFilters\Enums\FilterOperator;
use Dashworthy\ApiFilters\Enums\FilterSetOperator;
use Dashworthy\ApiFilters\Filtering\FilterApplier;
use Dashworthy\ApiFilters\Relations\RelationAllowList;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Validates a request's filter_sets and turns them into typed filter sets. Each
 * value is checked against the rules of every operation the applier will hand
 * its filter to. It reports every problem at once, keyed by input path, and
 * applies nothing, so a rejected request leaves the query untouched.
 */
final class FilterSetParser
{
    /**
     * @var (Closure(mixed): mixed)|null
     */
    private ?Closure $pipeline = null;

    public function __construct(
        private readonly ColumnResolver $columns,
        private readonly RelationAllowList $relations,
        private readonly FilterApplier $applier,
    ) {}

    /**
     * @return list<FilterSetData>
     *
     * @throws ValidationException
     */
    public function parse(mixed $filterSets, Model $model): array
    {
        /** @var array<string, list<string>> $errors */
        $errors = [];
        $parsed = [];
        $sets = $this->validateShape($filterSets);

        if (array_sum(array_map(count(...), array_column($sets, 'filters'))) > $this->limit('filters')) {
            $errors['filter_sets'][] = $this->message('too_many_filters', $this->limit('filters'));
        }

        foreach ($sets as $setIndex => $set) {
            $filters = [];

            foreach ($set['filters'] as $filterIndex => $filter) {
                $path = "filter_sets.{$setIndex}.filters.{$filterIndex}";
                $target = $this->resolveField($model, $filter['field'], $filter['filter_operator']);
                $filterData = new FilterData(
                    $target['column'] ?? '',
                    $filter['filter_operator'],
                    $this->normalize($filter['value']),
                    $target['relation'] ?? null,
                );
                $operations = $this->applier->operationsFor($filterData);
                $valueErrors = $operations === [] ? [] : $this->valueErrors($operations, $filterData->value);

                if ($target === null) {
                    $errors["{$path}.field"][] = $this->message('not_filterable');
                }

                if ($operations === []) {
                    $errors["{$path}.filter_operator"][] = $this->message('unsupported_operator');
                }

                if ($valueErrors !== []) {
                    $errors["{$path}.value"] = $valueErrors;
                }

                $filters[] = $filterData;
            }

            $parsed[] = new FilterSetData(FilterSetOperator::from($set['filter_set_operator']), $filters);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $parsed;
    }

    /**
     * Check the input's shape with Laravel's validator, so a malformed request
     * gets Laravel's own messages before anything else is looked at.
     *
     * @return list<array{filter_set_operator: string, filters: list<array{field: string, filter_operator: string, value: mixed}>}>
     *
     * @throws ValidationException
     */
    private function validateShape(mixed $filterSets): array
    {
        $validated = Validator::make(['filter_sets' => $filterSets], [
            'filter_sets' => ['present', 'list', 'max:'.$this->limit('filter_sets')],
            'filter_sets.*.filter_set_operator' => ['required', Rule::enum(FilterSetOperator::class)],
            'filter_sets.*.filters' => ['required', 'list'],
            'filter_sets.*.filters.*.field' => ['required', 'string'],
            'filter_sets.*.filters.*.filter_operator' => ['required', 'string'],
            'filter_sets.*.filters.*.value' => ['present'],
        ], [
            'filter_sets.max' => $this->message('too_many_filter_sets', $this->limit('filter_sets')),
        ])->validate();

        /** @var list<array{filter_set_operator: string, filters: list<array{field: string, filter_operator: string, value: mixed}>}> */
        return $validated['filter_sets'];
    }

    /**
     * The column and relation path a field names, or null when it names
     * nothing filterable. A plain field is a qualified column of the query's
     * model. A dotted field is an allow-listed relation path and a column of
     * the model it reaches, which the applier qualifies inside the relation's
     * subquery. For doesntHave the whole field is the path. A client's path is
     * only looked up, never handed to the model: what comes back is the
     * developer's spelling from the allow-list.
     *
     * @return array{column: string, relation: string|null}|null
     */
    private function resolveField(Model $model, string $field, string $operator): ?array
    {
        if ($operator === FilterOperator::DoesntHave->value) {
            $relation = $this->relations->find($field);

            return $relation === null ? null : ['column' => '', 'relation' => $relation['path']];
        }

        if (! str_contains($field, '.')) {
            $column = $this->columns->resolve($model, $field);

            return $column === null ? null : ['column' => $column, 'relation' => null];
        }

        $relation = $this->relations->find(Str::beforeLast($field, '.'));
        $column = $relation === null ? null : $this->columns->column($relation['model'], Str::afterLast($field, '.'));

        return $relation === null || $column === null ? null : ['column' => $column, 'relation' => $relation['path']];
    }

    /**
     * The message for each rule of the operations that the value fails.
     *
     * @param  non-empty-list<FilterOperationContract>  $operations
     * @return list<string>
     */
    private function valueErrors(array $operations, mixed $value): array
    {
        $rules = array_merge(...array_map(fn (FilterOperationContract $operation): array => $operation->rules(), $operations));

        return array_values(Validator::make(['value' => $value], ['value' => $rules])->errors()->messages()['value'] ?? []);
    }

    /**
     * Run the value, or each element of a list, through the normalizer pipeline.
     */
    private function normalize(mixed $value): mixed
    {
        $pipeline = $this->pipeline();

        return is_array($value) ? array_map($pipeline, $value) : $pipeline($value);
    }

    /**
     * The configured normalizers chained in order, each handing its result to the next.
     *
     * @return Closure(mixed): mixed
     */
    private function pipeline(): Closure
    {
        if ($this->pipeline === null) {
            $pipeline = fn (mixed $value): mixed => $value;

            foreach (array_reverse(array_values(Config::array('api-filters.normalizers'))) as $class) {
                $normalizer = is_string($class) ? Container::getInstance()->make($class) : $class;

                if (! $normalizer instanceof NormalizerContract) {
                    throw new LogicException('Every entry in api-filters.normalizers must implement '.NormalizerContract::class.'.');
                }

                $pipeline = fn (mixed $value): mixed => $normalizer->handle($value, $pipeline);
            }

            $this->pipeline = $pipeline;
        }

        return $this->pipeline;
    }

    private function limit(string $name): int
    {
        return Config::integer("api-filters.limits.{$name}");
    }

    private function message(string $key, ?int $max = null): string
    {
        $message = Lang::get("api-filters::validation.{$key}", $max === null ? [] : ['max' => (string) $max]);

        return is_string($message) ? $message : $key;
    }
}
