<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\Http;

use Closure;
use Dashworthy\ApiFilters\ApiFilters;
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
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * A form request for an endpoint that takes filter_sets, sorts and a first and
 * last window. It checks what the filter operations decide, without knowing
 * the model: the shape and limits of the input, an operator that an operation
 * in api-filters.operations handles, and a value that passes the rules of
 * every operation that handles it, once the configured normalizers have read
 * it. Which fields a client may use depends on the query's model, so the
 * engine checks them when it applies the filters and sorts. Extend it, and
 * override allowedRelations() to let clients filter and sort through
 * relations. Authorization stays with each endpoint's own request.
 */
abstract class FilterableRequest extends FormRequest
{
    /**
     * The name a filter's value goes by in validation messages.
     */
    private const array ATTRIBUTES = ['filter_sets.*.filters.*.value' => 'value'];

    /**
     * The relation paths this endpoint lets clients filter and sort through.
     *
     * @return list<string>
     */
    public function allowedRelations(): array
    {
        return [];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $window = ApiFilters::windowRules($this->input('first'));

        return [
            ...self::filterSetRules($this->input('filter_sets')),
            ...self::sortRules(),
            'first' => ['required_with:last', ...$window['first']],
            'last' => ['required_with:first', ...$window['last']],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [...self::filterSetMessages(), ...self::sortMessages()];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return self::ATTRIBUTES;
    }

    /**
     * Check filter_sets that do not come through a request as the request
     * checks them, and return them with each value normalized.
     *
     * @return list<array{filter_set_operator: string, filters: list<array{field: string, filter_operator: string, value: mixed}>}>
     *
     * @throws ValidationException
     */
    public static function validateFilterSets(mixed $filterSets): array
    {
        $filterSets = self::normalize($filterSets);
        $rules = self::filterSetRules($filterSets);
        $rules['filter_sets'] = ['present', ...$rules['filter_sets']];

        $validated = Validator::make(['filter_sets' => $filterSets], $rules, self::filterSetMessages(), self::ATTRIBUTES)->validate();

        /** @var list<array{filter_set_operator: string, filters: list<array{field: string, filter_operator: string, value: mixed}>}> */
        return $validated['filter_sets'];
    }

    /**
     * Check sorts that do not come through a request as the request checks them.
     *
     * @return list<array{field: string, sort_operator: string}>
     *
     * @throws ValidationException
     */
    public static function validateSorts(mixed $sorts): array
    {
        $rules = self::sortRules();
        $rules['sorts'] = ['present', ...$rules['sorts']];

        $validated = Validator::make(['sorts' => $sorts], $rules, self::sortMessages())->validate();

        /** @var list<array{field: string, sort_operator: string}> */
        return $validated['sorts'];
    }

    /**
     * Read each filter's value through the normalizers before it is checked.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('filter_sets')) {
            $this->merge(['filter_sets' => self::normalize($this->input('filter_sets'))]);
        }
    }

    /**
     * The rules for filter_sets: its shape and limits, and for each filter an
     * operator that an operation handles and a value that passes the rules of
     * every operation that handles it. Input over a limit gets no rules of
     * its own filters, so its size alone decides the work. The caller decides
     * whether filter_sets must be present.
     *
     * Each filter's own keys come before the wildcard keys: Laravel merges a
     * wildcard's rules into the keys it matches, and a matching key that
     * comes later in the list replaces that merge.
     *
     * @return array<string, list<mixed>>
     */
    private static function filterSetRules(mixed $filterSets): array
    {
        $filterCount = self::filterCount($filterSets);
        $isOverLimit = (is_array($filterSets) && count($filterSets) > self::limit('filter_sets')) || $filterCount > self::limit('filters');

        return [
            ...($isOverLimit ? [] : self::operationRules($filterSets)),
            'filter_sets' => [
                'list',
                'max:'.self::limit('filter_sets'),
                function (string $attribute, mixed $value, Closure $fail) use ($filterCount): void {
                    if ($filterCount > self::limit('filters')) {
                        $fail(self::message('too_many_filters', self::limit('filters')));
                    }
                },
            ],
            'filter_sets.*.filter_set_operator' => ['required', Rule::enum(FilterSetOperator::class)],
            'filter_sets.*.filters' => ['required', 'list'],
            'filter_sets.*.filters.*.field' => ['required', 'string'],
            'filter_sets.*.filters.*.filter_operator' => ['required', 'string'],
            'filter_sets.*.filters.*.value' => ['present'],
        ];
    }

    /**
     * For each filter, keyed by its input path, a rule that refuses an
     * operator no operation handles, or the rules of every operation that
     * handles it for the value.
     *
     * @return array<string, list<mixed>>
     */
    private static function operationRules(mixed $filterSets): array
    {
        $applier = FilterApplier::fromConfig();
        $rules = [];

        foreach (self::filters($filterSets) as $path => $filter) {
            $operations = $applier->operationsFor(new FilterData($filter['field'], $filter['filter_operator'], $filter['value']));

            if ($operations === []) {
                $rules["{$path}.filter_operator"] = [function (string $attribute, mixed $value, Closure $fail): void {
                    $fail(self::message('unsupported_operator'));
                }];

                continue;
            }

            $rules["{$path}.value"] = array_merge(...array_map(fn (FilterOperationContract $operation): array => $operation->rules(), $operations));
        }

        return $rules;
    }

    /**
     * The rules for the shape of sorts. The caller decides whether sorts must
     * be present.
     *
     * @return array<string, list<mixed>>
     */
    private static function sortRules(): array
    {
        return [
            'sorts' => ['list', 'max:'.self::limit('sorts')],
            'sorts.*.field' => ['required', 'string'],
            'sorts.*.sort_operator' => ['required', Rule::enum(SortOperator::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function filterSetMessages(): array
    {
        return ['filter_sets.max' => self::message('too_many_filter_sets', self::limit('filter_sets'))];
    }

    /**
     * @return array<string, string>
     */
    private static function sortMessages(): array
    {
        return ['sorts.max' => self::message('too_many_sorts', self::limit('sorts'))];
    }

    /**
     * The filters of the input, keyed by input path, where a set and a filter
     * have the shape their own rules check for.
     *
     * @return array<string, array{field: string, filter_operator: string, value: mixed}>
     */
    private static function filters(mixed $filterSets): array
    {
        $filters = [];

        foreach (is_array($filterSets) ? $filterSets : [] as $setIndex => $set) {
            foreach (is_array($set) && is_array($set['filters'] ?? null) ? $set['filters'] : [] as $filterIndex => $filter) {
                if (is_array($filter) && is_string($filter['field'] ?? null) && is_string($filter['filter_operator'] ?? null)) {
                    $filters["filter_sets.{$setIndex}.filters.{$filterIndex}"] = [
                        'field' => $filter['field'],
                        'filter_operator' => $filter['filter_operator'],
                        'value' => $filter['value'] ?? null,
                    ];
                }
            }
        }

        return $filters;
    }

    /**
     * How many filters the input's sets hold, whatever their shape.
     */
    private static function filterCount(mixed $filterSets): int
    {
        $count = 0;

        foreach (is_array($filterSets) ? $filterSets : [] as $set) {
            $count += is_array($set) && is_array($set['filters'] ?? null) ? count($set['filters']) : 0;
        }

        return $count;
    }

    /**
     * The input with each filter's value, or each element of a list value,
     * run through the normalizer pipeline. Anything else is left as it is.
     */
    private static function normalize(mixed $filterSets): mixed
    {
        if (! is_array($filterSets)) {
            return $filterSets;
        }

        $pipeline = self::pipeline();
        $normalizeFilter = fn (mixed $filter): mixed => is_array($filter) && array_key_exists('value', $filter)
            ? ['value' => is_array($filter['value']) ? array_map($pipeline, $filter['value']) : $pipeline($filter['value'])] + $filter
            : $filter;

        return array_map(
            fn (mixed $set): mixed => is_array($set) && is_array($set['filters'] ?? null)
                ? ['filters' => array_map($normalizeFilter, $set['filters'])] + $set
                : $set,
            $filterSets,
        );
    }

    /**
     * The normalizers api-filters.normalizers lists, chained in order, each
     * handing its result to the next.
     *
     * @return Closure(mixed): mixed
     */
    private static function pipeline(): Closure
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
