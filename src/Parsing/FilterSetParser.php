<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\Parsing;

use Closure;
use Dashworthy\ApiFilters\Columns\ColumnResolver;
use Dashworthy\ApiFilters\Contracts\NormalizerContract;
use Dashworthy\ApiFilters\Data\FilterSetData;
use Dashworthy\ApiFilters\Enums\FilterOperator;
use Dashworthy\ApiFilters\Enums\FilterSetOperator;
use DateTimeImmutable;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Validates a request's filter_sets and turns them into typed filter sets. It
 * reports every problem at once, keyed by input path, and applies nothing, so
 * a rejected request leaves the query untouched.
 */
final class FilterSetParser
{
    /**
     * @var (Closure(mixed): mixed)|null
     */
    private ?Closure $pipeline = null;

    public function __construct(private readonly ColumnResolver $columns) {}

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
                $operator = FilterOperator::from($filter['filter_operator']);
                $value = $this->normalize($filter['value']);
                $column = $this->resolveColumn($model, $filter['field']);

                if ($column === null) {
                    $errors["{$path}.field"][] = $this->message('not_filterable');
                }

                $valueError = $this->valueError($operator, $value);

                if ($valueError !== null) {
                    $errors["{$path}.value"][] = $valueError;
                }

                if ($column !== null && $valueError === null) {
                    /** @var scalar|list<scalar|null>|null $value */
                    $filters[] = ['column' => $column, 'filter_operator' => $operator->value, 'value' => $value];
                }
            }

            $parsed[] = ['filter_set_operator' => $set['filter_set_operator'], 'filters' => $filters];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return array_map(FilterSetData::fromValidated(...), $parsed);
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
            'filter_sets.*.filters.*.filter_operator' => ['required', Rule::enum(FilterOperator::class)],
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
     * Why the value does not fit its operator, or null when it does.
     */
    private function valueError(FilterOperator $operator, mixed $value): ?string
    {
        if (in_array($operator, [FilterOperator::In, FilterOperator::NotIn], true)
            && is_array($value)
            && count($value) > $this->limit('in_values')) {
            return $this->message('too_many_values', $this->limit('in_values'));
        }

        return $this->fits($operator, $value) ? null : $this->message('invalid_value');
    }

    private function fits(FilterOperator $operator, mixed $value): bool
    {
        return match ($operator) {
            FilterOperator::Equals, FilterOperator::DoesNotEquals => $value === null || is_scalar($value),
            FilterOperator::Lt, FilterOperator::Lte, FilterOperator::Gt, FilterOperator::Gte => is_scalar($value),
            FilterOperator::In, FilterOperator::NotIn => is_array($value)
                && $value !== []
                && array_is_list($value)
                && array_filter($value, fn (mixed $element): bool => $element !== null && ! is_scalar($element)) === [],
            FilterOperator::StartsWith,
            FilterOperator::Contains,
            FilterOperator::EndsWith,
            FilterOperator::DoesNotContain => is_string($value) || is_int($value) || is_float($value),
            FilterOperator::DateIs,
            FilterOperator::DateIsNot,
            FilterOperator::DateBefore,
            FilterOperator::DateAfter => is_string($value) && $this->isCalendarDate($value),
        };
    }

    private function isCalendarDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
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
