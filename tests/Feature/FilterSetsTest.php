<?php

declare(strict_types=1);

use Dashworthy\ApiFilters\ApiFilters;
use Dashworthy\ApiFilters\Contracts\FilterOperationContract;
use Dashworthy\ApiFilters\Data\FilterData;
use Dashworthy\ApiFilters\Http\FilterableRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Workbench\App\Models\Order;

mutates(FilterableRequest::class, ApiFilters::class);

/*
 | $errors applies filter sets to an orders query and returns the validation
 | errors, keyed by input path. $sql returns the SQL they make of the query.
 | $set and $filter build the wire format.
 */
$errors = function (mixed $filterSets, array $allowedRelations = []): array {
    try {
        ApiFilters::for(Order::query())->allowedRelations($allowedRelations)->filter($filterSets);
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
};

$sql = fn (array $filterSets, array $allowedRelations = []): string => ApiFilters::for(Order::query())
    ->allowedRelations($allowedRelations)
    ->filter($filterSets)
    ->query()
    ->toRawSql();

$filter = fn (string $field, string $operator, mixed $value): array => [
    'field' => $field,
    'filter_operator' => $operator,
    'value' => $value,
];

$set = fn (array ...$filters): array => ['filter_set_operator' => 'and', 'filters' => $filters];

it('filters by the qualified columns, spelled as the model lists them', function () use ($filter, $sql) {
    expect($sql([['filter_set_operator' => 'or', 'filters' => [$filter('STATUS', 'equals', 'open'), $filter('notes', 'in', ['null', 'rush'])]]]))
        ->toContain("`orders`.`status` = 'open'")
        ->toContain("`orders`.`notes` in ('rush') or `orders`.`notes` is null");
});

describe('unsupported operators are rejected', function () use ($errors, $filter, $set) {
    it('rejects an operator no operation can handle', function () use ($errors, $filter, $set) {
        expect($errors([$set($filter('total', 'between', [1, 2]))]))
            ->toBe(['filter_sets.0.filters.0.filter_operator' => ['This filter operator is not supported.']]);
    });

    it('reports an unknown operator before it checks the field', function () use ($errors, $filter, $set) {
        expect(array_keys($errors([$set($filter('nope', 'between', [1, 2]))])))
            ->toBe(['filter_sets.0.filters.0.filter_operator']);
    });
});

describe('values are checked against the rules of the operation that handles them', function () use ($errors, $filter, $set, $sql) {
    $between = new class implements FilterOperationContract
    {
        public function canHandle(FilterData $filterData): bool
        {
            return $filterData->operator === 'between';
        }

        public function rules(): array
        {
            return ['required', 'list', 'size:2'];
        }

        public function handle(Builder $query, FilterData $filterData): Builder
        {
            return $query->whereBetween($filterData->column, $filterData->listValue());
        }
    };

    it('accepts a value that passes the rules', function () use ($between, $filter, $set, $sql) {
        config()->set('api-filters.operations', [$between]);

        expect($sql([$set($filter('total', 'between', [1, 2]))]))->toContain('`orders`.`total` between 1 and 2');
    });

    it('checks the value against every operation that can handle the filter', function () use ($between, $errors, $filter, $set) {
        $positive = new class implements FilterOperationContract
        {
            public function canHandle(FilterData $filterData): bool
            {
                return $filterData->operator === 'between';
            }

            public function rules(): array
            {
                return [fn (string $attribute, mixed $value, Closure $fail) => min((array) $value) < 0 ? $fail('The :attribute must not be negative.') : null];
            }

            public function handle(Builder $query, FilterData $filterData): Builder
            {
                return $query;
            }
        };
        config()->set('api-filters.operations', [$between, $positive]);

        expect($errors([$set($filter('total', 'between', [-1, 2]))]))
            ->toBe(['filter_sets.0.filters.0.value' => ['The value must not be negative.']]);
    });

    it('reports the rules a value fails', function () use ($between, $errors, $filter, $set) {
        config()->set('api-filters.operations', [$between]);

        expect($errors([$set($filter('total', 'between', [1]))]))
            ->toBe(['filter_sets.0.filters.0.value' => ['The value field must contain 2 items.']]);
    });
});

describe('filter values must fit their operator', function () use ($errors, $filter, $set) {
    it('requires a list for in', function () use ($errors, $filter, $set) {
        expect($errors([$set($filter('status', 'in', 'open'))]))
            ->toBe(['filter_sets.0.filters.0.value' => ['The value field must be a list.']]);
    });

    it('rejects an empty in list', function () use ($errors, $filter, $set) {
        expect(array_keys($errors([$set($filter('status', 'notIn', []))])))->toBe(['filter_sets.0.filters.0.value']);
    });

    it('rejects a nested list for in', function () use ($errors, $filter, $set) {
        expect($errors([$set($filter('status', 'in', [['open']]))]))
            ->toBe(['filter_sets.0.filters.0.value' => ['This value does not fit the filter operator.']]);
    });

    it('rejects a keyed list for in', function () use ($errors, $filter, $set) {
        expect(array_keys($errors([$set($filter('status', 'in', ['a' => 'open']))])))->toBe(['filter_sets.0.filters.0.value']);
    });

    it('rejects a list for comparison', function () use ($errors, $filter, $set) {
        expect(array_keys($errors([$set($filter('total', 'lt', [1, 2]))])))->toBe(['filter_sets.0.filters.0.value']);
    });

    it('rejects null for comparison', function () use ($errors, $filter, $set) {
        expect(array_keys($errors([$set($filter('total', 'gt', null))])))->toBe(['filter_sets.0.filters.0.value']);
    });

    it('rejects a list for equality', function () use ($errors, $filter, $set) {
        expect(array_keys($errors([$set($filter('status', 'equals', ['open', 'closed']))])))->toBe(['filter_sets.0.filters.0.value']);
    });

    it('rejects a boolean for a pattern', function () use ($errors, $filter, $set) {
        expect(array_keys($errors([$set($filter('notes', 'contains', true))])))->toBe(['filter_sets.0.filters.0.value']);
    });

    it('accepts a number for a pattern', function () use ($errors, $filter, $set) {
        expect($errors([$set($filter('notes', 'startsWith', 5), $filter('notes', 'endsWith', 1.5))]))->toBe([]);
    });

    it('requires a calendar date for date operators', function (mixed $value) use ($errors, $filter, $set) {
        expect(array_keys($errors([$set($filter('placed_at', 'dateIs', $value))])))->toBe(['filter_sets.0.filters.0.value']);
    })->with([
        'a word' => 'yesterday',
        'a date that does not exist' => '2026-02-30',
        'a date and time' => '2026-01-15 09:00:00',
        'a number' => 20260115,
    ]);

    it('reports every value problem at once', function () use ($errors, $filter, $set) {
        expect(array_keys($errors([$set($filter('nope', 'gt', null), $filter('total', 'in', 'x'))])))->toBe([
            'filter_sets.0.filters.0.value',
            'filter_sets.0.filters.1.value',
        ]);
    });

    it('reports every field problem at once', function () use ($errors, $filter, $set) {
        expect(array_keys($errors([$set($filter('nope', 'equals', 'x')), $set($filter('status', 'equals', 'open'), $filter('internal_reference', 'equals', 'x'))])))->toBe([
            'filter_sets.0.filters.0.field',
            'filter_sets.1.filters.1.field',
        ]);
    });
});

describe('input size is limited', function () use ($errors, $filter, $set) {
    it('rejects too many filter sets', function () use ($errors, $filter, $set) {
        expect($errors(array_fill(0, 11, $set($filter('status', 'equals', 'open')))))
            ->toBe(['filter_sets' => ['No more than 10 filter sets may be sent.']])
            ->and($errors(array_fill(0, 10, $set($filter('status', 'equals', 'open')))))->toBe([]);
    });

    it('rejects too many filters in total', function () use ($errors, $filter, $set) {
        $filters = fn (int $count): array => $set(...array_fill(0, $count, $filter('status', 'equals', 'open')));

        expect($errors([$filters(26), $filters(25)]))
            ->toBe(['filter_sets' => ['No more than 50 filters may be sent across all filter sets.']])
            ->and($errors([$filters(25), $filters(25)]))->toBe([]);
    });

    it('rejects too many in values', function () use ($errors, $filter, $set) {
        expect($errors([$set($filter('status', 'in', range(1, 101)))]))
            ->toBe(['filter_sets.0.filters.0.value' => ['The value field must not have more than 100 items.']])
            ->and($errors([$set($filter('status', 'notIn', range(1, 100)))]))->toBe([]);
    });

    it('reads limits from configuration', function () use ($errors, $filter, $set) {
        config()->set('api-filters.limits', ['filter_sets' => 1, 'filters' => 1, 'in_values' => 1]);

        expect(array_keys($errors([$set($filter('status', 'equals', 'open')), $set($filter('status', 'equals', 'open'))])))->toBe(['filter_sets'])
            ->and(array_keys($errors([$set($filter('status', 'equals', 'open'), $filter('status', 'equals', 'open'))])))->toBe(['filter_sets'])
            ->and(array_keys($errors([$set($filter('status', 'in', ['open', 'closed']))])))->toBe(['filter_sets.0.filters.0.value']);
    });
});

describe('malformed input is rejected before any query runs', function () use ($errors, $filter) {
    it('requires a set operator', function () use ($errors, $filter) {
        expect(array_keys($errors([['filters' => [$filter('status', 'equals', 'open')]]])))->toBe(['filter_sets.0.filter_set_operator']);
    });

    it('rejects an unknown set operator', function () use ($errors, $filter) {
        expect(array_keys($errors([['filter_set_operator' => 'xor', 'filters' => [$filter('status', 'equals', 'open')]]])))
            ->toBe(['filter_sets.0.filter_set_operator']);
    });

    it('rejects an empty filters list', function () use ($errors) {
        expect(array_keys($errors([['filter_set_operator' => 'and', 'filters' => []]])))->toBe(['filter_sets.0.filters']);
    });

    it('requires filter sets to be a list', function (mixed $filterSets) use ($errors) {
        expect(array_keys($errors($filterSets)))->toBe(['filter_sets']);
    })->with([
        'a string' => 'status',
        'null' => [null],
        'keyed sets' => [['first' => [
            'filter_set_operator' => 'and',
            'filters' => [['field' => 'status', 'filter_operator' => 'equals', 'value' => 'open']],
        ]]],
    ]);

    it('requires a string field', function () use ($errors) {
        expect(array_keys($errors([['filter_set_operator' => 'and', 'filters' => [['field' => ['status'], 'filter_operator' => 'equals', 'value' => 'open']]]])))
            ->toBe(['filter_sets.0.filters.0.field']);
    });

    it('requires a value key', function () use ($errors) {
        expect(array_keys($errors([['filter_set_operator' => 'and', 'filters' => [['field' => 'status', 'filter_operator' => 'equals']]]])))
            ->toBe(['filter_sets.0.filters.0.value']);
    });
});

describe('relation paths are denied by default', function () use ($errors, $filter, $set) {
    it('rejects a relation field without an allow-list', function () use ($errors, $filter, $set) {
        expect($errors([$set($filter('customer.name', 'equals', 'Ada'))]))
            ->toBe(['filter_sets.0.filters.0.field' => ['This field cannot be filtered.']]);
    });
});

describe('relations', function () use ($errors, $filter, $set, $sql) {
    it('filters through the developer\'s path by the related model\'s spelling of a column', function () use ($filter, $sql) {
        expect($sql([['filter_set_operator' => 'and', 'filters' => [$filter('line_items.product.SKU', 'equals', 'SKU-1'), $filter('line_items', 'doesntHave', null)]]], ['lineItems.product']))
            ->toContain("`products`.`sku` = 'SKU-1'")
            ->toContain('not exists (select * from `line_items`');
    });

    it('keeps other relations denied', function () use ($errors, $filter, $set) {
        expect($errors([$set($filter('customer.name', 'equals', 'Ada'))], ['lineItems']))
            ->toBe(['filter_sets.0.filters.0.field' => ['This field cannot be filtered.']]);
    });

    it('rejects a hidden related column', function () use ($errors, $filter, $set) {
        expect(array_keys($errors([$set($filter('customer.password', 'startsWith', '$2y$'))], ['customer'])))
            ->toBe(['filter_sets.0.filters.0.field']);
    });

    it('uses one message for hidden and unknown related columns', function () use ($errors, $filter, $set) {
        expect($errors([$set($filter('customer.password', 'equals', 'x'))], ['customer']))
            ->toBe(['filter_sets.0.filters.0.field' => ['This field cannot be filtered.']])
            ->and($errors([$set($filter('customer.nope', 'equals', 'x'))], ['customer']))
            ->toBe($errors([$set($filter('customer.password', 'equals', 'x'))], ['customer']));
    });

    it('rejects a doesnt have path that is not allow-listed', function () use ($errors, $filter, $set) {
        expect($errors([$set($filter('lineItems', 'doesntHave', null))]))
            ->toBe(['filter_sets.0.filters.0.field' => ['This field cannot be filtered.']]);
    });
});

it('rejects a normalizer that does not implement the contract', function () use ($errors, $filter, $set) {
    config()->set('api-filters.normalizers', [stdClass::class]);

    $errors([$set($filter('status', 'equals', 'open'))]);
})->throws(LogicException::class, 'Every entry in api-filters.normalizers must implement Dashworthy\ApiFilters\Contracts\NormalizerContract.');
