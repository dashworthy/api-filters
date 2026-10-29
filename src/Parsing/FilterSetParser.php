<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\Parsing;

use Closure;
use Dashworthy\ApiFilters\Columns\ColumnResolver;
use Dashworthy\ApiFilters\Contracts\FilterOperationContract;
use Dashworthy\ApiFilters\Contracts\NormalizerContract;
use Dashworthy\ApiFilters\Data\FilterData;
use Dashworthy\ApiFilters\Data\FilterSetData;
use Dashworthy\ApiFilters\Enums\FilterSetOperator;
use Dashworthy\ApiFilters\Filtering\FilterApplier;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Validates a request's filter_sets and turns them into typed filter sets. Each
 * value is checked against the rules of the operation the applier will hand
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
                $column = $this->resolveColumn($model, $filter['field']);
                $filterData = new FilterData($column ?? '', $filter['filter_operator'], $this->normalize($filter['value']));
                $operation = $this->applier->operationFor($filterData);
                $valueErrors = $operation === null ? [] : $this->valueErrors($operation, $filterData->value);

                if ($column === null) {
                    $errors["{$path}.field"][] = $this->message('not_filterable');
                }

                if ($operation === null) {
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
     * The qualified column a field names. A dotted field is a relation path,
     * and no relation is filterable until the developer allows it, so it is
     * rejected without ever reaching the model.
     */
    private function resolveColumn(Model $model, string $field): ?string
    {
        if (str_contains($field, '.')) {
            return null;
        }

        return $this->columns->resolve($model, $field);
    }

    /**
     * The message for each of the operation's rules that the value fails.
     *
     * @return list<string>
     */
    private function valueErrors(FilterOperationContract $operation, mixed $value): array
    {
        return array_values(Validator::make(['value' => $value], ['value' => $operation->rules()])->errors()->messages()['value'] ?? []);
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
