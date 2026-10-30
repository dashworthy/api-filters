<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\Http;

use Closure;
use Dashworthy\ApiFilters\Contracts\FilterOperationContract;
use Dashworthy\ApiFilters\Contracts\NormalizerContract;
use Dashworthy\ApiFilters\Data\FilterData;
use Dashworthy\ApiFilters\Enums\FilterSetOperator;
use Dashworthy\ApiFilters\Enums\SortOperator;
use Dashworthy\ApiFilters\Filtering\FilterApplier;
use Illuminate\Container\Container;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use LogicException;

/**
 * The request an endpoint takes filter_sets and sorts through. It checks their
 * shape and limits, that an operation handles each filter's operator, and each
 * value against the rules of the operations that handle it, once the
 * normalizers have read it. apiFiltersAndSorts() then checks each field
 * against the query model's #[Filterable]. Extend it and add authorize().
 */
abstract class FilterableRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter_sets' => ['list', 'max:'.self::limit('filter_sets'), function (string $attribute, mixed $value, Closure $fail): void {
                if ($this->collect('filter_sets.*.filters.*')->count() > self::limit('filters')) {
                    $fail(self::message('too_many_filters', self::limit('filters')));
                }
            }],
            'filter_sets.*.filter_set_operator' => ['required', Rule::enum(FilterSetOperator::class)],
            'filter_sets.*.filters' => ['required', 'list'],
            'filter_sets.*.filters.*.field' => ['required', 'string'],
            'filter_sets.*.filters.*.filter_operator' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if ($this->operations(Str::beforeLast($attribute, '.')) === []) {
                    $fail(self::message('unsupported_operator'));
                }
            }],
            'filter_sets.*.filters.*.value' => Rule::forEach(fn (mixed $value, string $attribute): array => [
                'present',
                ...array_merge(...array_map(fn (FilterOperationContract $operation): array => $operation->rules(), $this->operations(Str::beforeLast($attribute, '.')))),
            ]),
            'sorts' => ['list', 'max:'.self::limit('sorts')],
            'sorts.*.field' => ['required', 'string'],
            'sorts.*.sort_operator' => ['required', Rule::enum(SortOperator::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'filter_sets.max' => self::message('too_many_filter_sets', self::limit('filter_sets')),
            'sorts.max' => self::message('too_many_sorts', self::limit('sorts')),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['filter_sets.*.filters.*.value' => 'value'];
    }

    /**
     * @return list<array{filter_set_operator: string, filters: list<array{field: string, filter_operator: string, value: mixed}>}>
     */
    public function filterSets(): array
    {
        /** @var list<array{filter_set_operator: string, filters: list<array{field: string, filter_operator: string, value: mixed}>}> */
        return $this->validated('filter_sets', []);
    }

    /**
     * @return list<array{field: string, sort_operator: string}>
     */
    public function sorts(): array
    {
        /** @var list<array{field: string, sort_operator: string}> */
        return $this->validated('sorts', []);
    }

    /**
     * Read each value, or each element of a list value, through the
     * normalizers, so the rules check what the operations will receive.
     */
    protected function prepareForValidation(): void
    {
        $filterSets = $this->input('filter_sets');

        if (! is_array($filterSets)) {
            return;
        }

        $normalize = self::normalizer();

        foreach ($filterSets as $setIndex => $set) {
            foreach (is_array($set) && is_array($set['filters'] ?? null) ? $set['filters'] : [] as $filterIndex => $filter) {
                if (is_array($filter) && array_key_exists('value', $filter)) {
                    data_set($filterSets, "{$setIndex}.filters.{$filterIndex}.value", is_array($filter['value']) ? array_map($normalize, $filter['value']) : $normalize($filter['value']));
                }
            }
        }

        $this->merge(['filter_sets' => $filterSets]);
    }

    /**
     * The operations that handle the filter at the input path.
     *
     * @return list<FilterOperationContract>
     */
    private function operations(string $filterPath): array
    {
        $field = $this->input("{$filterPath}.field");
        $operator = $this->input("{$filterPath}.filter_operator");

        return FilterApplier::fromConfig()->operationsFor(new FilterData(
            is_string($field) ? $field : '',
            is_string($operator) ? $operator : '',
            $this->input("{$filterPath}.value"),
        ));
    }

    /**
     * The normalizers api-filters.normalizers lists, chained in order.
     *
     * @return Closure(mixed): mixed
     */
    private static function normalizer(): Closure
    {
        $pipeline = fn (mixed $value): mixed => $value;

        foreach (array_reverse(array_values(Config::array('api-filters.normalizers'))) as $class) {
            $normalizer = is_string($class) ? Container::getInstance()->make($class) : $class;

            if (! $normalizer instanceof NormalizerContract) {
                throw new LogicException('Every entry in api-filters.normalizers must implement '.NormalizerContract::class.'.');
            }

            $pipeline = fn (mixed $value): mixed => $normalizer->handle($value, $pipeline);
        }

        return $pipeline;
    }

    private static function limit(string $name): int
    {
        return Config::integer("api-filters.limits.{$name}");
    }

    private static function message(string $key, ?int $max = null): string
    {
        $message = Lang::get("api-filters::validation.{$key}", $max === null ? [] : ['max' => (string) $max]);

        return is_string($message) ? $message : $key;
    }
}
