<?php

declare(strict_types=1);

use Dashworthy\ApiFilters\ApiFilters;
use Dashworthy\ApiFilters\Contracts\FilterOperationContract;
use Dashworthy\ApiFilters\Data\FilterData;
use Dashworthy\ApiFilters\FilterOperations\Equality\Equals;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Customer;
use Workbench\App\Models\ListedOrder;
use Workbench\App\Models\Order;

mutates(ApiFilters::class);

/*
 | $codes filters an orders query and returns the matching orders' codes.
 | $set and $filter build the wire format.
 */
$codes = fn (array $filterSets, ?Builder $query = null): array => (new ApiFilters($query ?? Order::query()))
    ->apply(filterableRequest(['filter_sets' => $filterSets]))
    ->orderBy('code')
    ->pluck('code')
    ->all();

$filter = fn (string $field, string $operator, mixed $value): array => [
    'field' => $field,
    'filter_operator' => $operator,
    'value' => $value,
];

$set = fn (string $operator, array ...$filters): array => ['filter_set_operator' => $operator, 'filters' => $filters];

describe('filter sets narrow the query', function () use ($codes, $filter, $set) {
    beforeEach(fn () => Order::factory()->createMany([
        ['code' => 'open-50', 'status' => 'open', 'total' => 50],
        ['code' => 'open-500', 'status' => 'open', 'total' => 500],
        ['code' => 'closed-500', 'status' => 'closed', 'total' => 500],
    ]));

    it('applies an and set', function () use ($codes, $filter, $set) {
        expect($codes([$set('and', $filter('status', 'equals', 'open'), $filter('total', 'gt', 100))]))->toBe(['open-500']);
    });

    it('applies an or set', function () use ($codes, $filter, $set) {
        expect($codes([$set('or', $filter('status', 'equals', 'closed'), $filter('total', 'lt', 100))]))->toBe(['closed-500', 'open-50']);
    });

    it('joins sets with and', function () use ($codes, $filter, $set) {
        expect($codes([
            $set('or', $filter('status', 'equals', 'open'), $filter('status', 'equals', 'closed')),
            $set('and', $filter('total', 'gt', 100)),
        ]))->toBe(['closed-500', 'open-500']);
    });

    it('returns every row for no filter sets', function () use ($codes) {
        expect($codes([]))->toBe(['closed-500', 'open-50', 'open-500']);
    });
});

describe('filters never widen the developer\'s constraints', function () use ($codes, $filter, $set) {
    it('keeps an or set inside the developer\'s constraints', function () use ($codes, $filter, $set) {
        Order::factory()->createMany([
            ['code' => 'team-1', 'team_id' => 1, 'status' => 'open'],
            ['code' => 'team-2', 'team_id' => 2, 'status' => 'closed'],
        ]);

        expect($codes(
            [$set('or', $filter('status', 'equals', 'closed'), $filter('status', 'equals', 'open'))],
            Order::query()->where('team_id', 1),
        ))->toBe(['team-1']);
    });
});

describe('malformed input is rejected before any query runs', function () use ($filter, $set) {
    it('leaves the query unchanged when input is rejected', function () use ($filter, $set) {
        $query = Order::query()->where('team_id', 1);
        $before = $query->toRawSql();

        try {
            (new ApiFilters($query))->apply(filterableRequest(['filter_sets' => [$set('and', $filter('status', 'equals', 'open'), $filter('nope', 'equals', 'x'))]]));
        } catch (ValidationException) {
        }

        expect($query->toRawSql())->toBe($before);
    });
});

describe('relation paths are denied by default', function () use ($filter, $set) {
    it('cannot truncate through a relation name', function () use ($filter, $set) {
        Order::factory()->count(2)->create();

        expect(fn () => (new ApiFilters(Order::query()))->apply(filterableRequest(['filter_sets' => [$set('and', $filter('truncate.id', 'equals', 1))]]))->get())
            ->toThrow(ValidationException::class)
            ->and(Order::query()->count())->toBe(2);
    });
});

describe('relation names from clients are never called', function () use ($filter, $set) {
    it('cannot truncate through an unlisted name', function () use ($filter, $set) {
        Order::factory()->count(2)->create();

        expect(fn () => (new ApiFilters(Order::query()))->apply(filterableRequest(['filter_sets' => [$set('and', $filter('truncate.id', 'equals', 1))]]))->get())
            ->toThrow(ValidationException::class)
            ->and(Order::query()->count())->toBe(2);
    });

    it('cannot save through an unlisted name', function () use ($filter, $set) {
        Order::factory()->count(2)->create();

        expect(fn () => (new ApiFilters(Order::query()))->apply(filterableRequest(['filter_sets' => [$set('and', $filter('save.id', 'equals', 1))]]))->get())
            ->toThrow(ValidationException::class)
            ->and(Order::query()->count())->toBe(2);
    });
});

describe('string tokens are normalized', function () use ($codes, $filter, $set) {
    it('matches null for the string null', function () use ($codes, $filter, $set) {
        createOrdersWithNotes();

        expect($codes([$set('and', $filter('notes', 'equals', 'null'))]))->toBe(['none']);
    });

    it('matches true for the string true', function () use ($codes, $filter, $set) {
        Order::factory()->createMany([['code' => 'priority', 'is_priority' => true], ['code' => 'normal', 'is_priority' => false]]);

        expect($codes([$set('and', $filter('is_priority', 'equals', 'true'))]))->toBe(['priority']);
    });

    it('normalizes list elements', function () use ($codes, $filter, $set) {
        createOrdersWithNotes();

        expect($codes([$set('and', $filter('notes', 'in', ['null', 'rush']))]))->toBe(['none', 'rush']);
    });

    it('keeps leading zeros', function () use ($codes, $filter, $set) {
        Order::factory()->createMany([['code' => '007'], ['code' => '7']]);

        expect($codes([$set('and', $filter('code', 'equals', '007'))]))->toBe(['007']);
    });
});

describe('apps add their own operations', function () use ($codes, $filter, $set) {
    /*
     | The README's example: keeps the rows whose point column lies inside a
     | polygon, sent as a closed ring of [longitude, latitude] positions.
     */
    $withinPolygon = new class implements FilterOperationContract
    {
        public function canHandle(FilterData $filterData): bool
        {
            return $filterData->operator === 'withinPolygon';
        }

        public function rules(): array
        {
            return [
                'bail',
                'required',
                'list',
                'min:4',
                'max:1000',
                function (string $attribute, array $ring, Closure $fail): void {
                    $isPosition = fn (mixed $position): bool => is_array($position) && array_is_list($position)
                        && count($position) === 2 && is_numeric($position[0]) && is_numeric($position[1]);

                    if (array_filter($ring, $isPosition) !== $ring || $ring[0] != $ring[array_key_last($ring)]) {
                        $fail('The :attribute must be a closed ring of [longitude, latitude] positions.');
                    }
                },
            ];
        }

        public function handle(Builder $query, FilterData $filterData): Builder
        {
            $ring = array_map(fn (array $position): array => array_map(floatval(...), $position), $filterData->value);
            $polygon = json_encode(['type' => 'Polygon', 'coordinates' => [$ring]], JSON_THROW_ON_ERROR);
            $column = $query->getQuery()->getGrammar()->wrap($filterData->column);

            return $query->whereRaw("ST_Contains(ST_GeomFromGeoJSON(?), {$column})", [$polygon]);
        }
    };

    $square = [[0, 0], [2, 0], [2, 2], [0, 2], [0, 0]];

    beforeEach(fn () => Order::factory()->createMany([
        ['code' => 'inside', 'team_id' => 1, 'status' => 'open', 'location' => DB::raw("ST_GeomFromText('POINT(1 1)')")],
        ['code' => 'outside', 'team_id' => 1, 'status' => 'open', 'location' => DB::raw("ST_GeomFromText('POINT(5 5)')")],
        ['code' => 'nowhere', 'team_id' => 1, 'status' => 'open'],
        ['code' => 'other-team', 'team_id' => 2, 'status' => 'closed', 'location' => DB::raw("ST_GeomFromText('POINT(1 1)')")],
    ]));

    it('filters with an operation listed in the config', function () use ($codes, $filter, $set, $square, $withinPolygon) {
        config()->push('api-filters.operations', $withinPolygon);

        expect($codes([$set('and', $filter('location', 'withinPolygon', $square))]))->toBe(['inside', 'other-team']);
    });

    it('checks the value against the operation\'s rules', function () use ($filter, $set, $withinPolygon) {
        config()->push('api-filters.operations', $withinPolygon);

        (new ApiFilters(Order::query()))->apply(filterableRequest(['filter_sets' => [$set('and', $filter('location', 'withinPolygon', [[0, 0], [2, 0], [2, 2], [0, 2]]))]]));
    })->throws(ValidationException::class, 'The value must be a closed ring of [longitude, latitude] positions.');

    it('keeps the operation inside the developer\'s constraints', function () use ($codes, $filter, $set, $square, $withinPolygon) {
        config()->push('api-filters.operations', $withinPolygon);

        expect($codes(
            [$set('or', $filter('location', 'withinPolygon', $square), $filter('status', 'equals', 'closed'))],
            Order::query()->where('team_id', 1),
        ))->toBe(['inside']);
    });

    it('replaces a built-in operation the config no longer lists', function () use ($codes, $filter, $set) {
        config()->set('api-filters.operations', [
            ...array_filter(config('api-filters.operations'), fn (string $operation): bool => $operation !== Equals::class),
            new class implements FilterOperationContract
            {
                public function canHandle(FilterData $filterData): bool
                {
                    return $filterData->operator === 'equals';
                }

                public function rules(): array
                {
                    return ['required', 'string'];
                }

                public function handle(Builder $query, FilterData $filterData): Builder
                {
                    return $query->whereLike($filterData->column, $filterData->stringValue().'%');
                }
            },
        ]);

        expect($codes([$set('and', $filter('code', 'equals', 'out'))]))->toBe(['outside']);
    });

    it('applies every operation that can handle a filter', function () use ($codes, $filter, $set) {
        config()->push('api-filters.operations', new class implements FilterOperationContract
        {
            public function canHandle(FilterData $filterData): bool
            {
                return $filterData->operator === 'equals';
            }

            public function rules(): array
            {
                return [];
            }

            public function handle(Builder $query, FilterData $filterData): Builder
            {
                return $query->whereNotNull('location');
            }
        });

        expect($codes([$set('and', $filter('status', 'equals', 'open'))]))->toBe(['inside', 'outside']);
    });

    it('refuses an entry that is not an operation', function () use ($filter, $set) {
        config()->push('api-filters.operations', stdClass::class);

        (new ApiFilters(Order::query()))->apply(filterableRequest(['filter_sets' => [$set('and', $filter('status', 'equals', 'open'))]]));
    })->throws(LogicException::class, 'Every entry in api-filters.operations must implement Dashworthy\ApiFilters\Contracts\FilterOperationContract.');
});

describe('models that list their columns with #[Filterable]', function () use ($filter, $set) {
    it('sorts only by the columns the model lists', function () {
        Order::factory()->createMany([['code' => 'small', 'total' => 50], ['code' => 'large', 'total' => 500]]);

        expect((new ApiFilters(ListedOrder::query()))->apply(filterableRequest(['sorts' => [['field' => 'total', 'sort_operator' => 'desc']]]))->pluck('code')->all())
            ->toBe(['large', 'small'])
            ->and(fn () => (new ApiFilters(ListedOrder::query()))->apply(filterableRequest(['sorts' => [['field' => 'notes', 'sort_operator' => 'asc']]])))
            ->toThrow(ValidationException::class, 'This field cannot be sorted.');
    });

    it('filters through a relation only by the columns the related model lists', function () use ($filter, $set) {
        Order::factory()->for(Customer::factory()->state(['name' => 'Ada']))->create(['code' => 'ada']);
        Order::factory()->for(Customer::factory()->state(['name' => 'Grace']))->create(['code' => 'grace']);
        $codes = fn (array $filter): array => (new ApiFilters(ListedOrder::query()))
            ->apply(filterableRequest(['filter_sets' => [$set('and', $filter)]]))
            ->pluck('code')
            ->all();

        expect($codes($filter('customer.name', 'equals', 'Ada')))->toBe(['ada'])
            ->and(fn () => $codes($filter('customer.email', 'equals', 'ada@example.com')))
            ->toThrow(ValidationException::class, 'This field cannot be filtered.');
    });
});

describe('a morph-to reaches a different model from row to row', function () use ($filter, $set) {
    it('refuses to filter by a column through it', function () use ($filter, $set) {
        expect(fn () => (new ApiFilters(Comment::query()))->apply(filterableRequest(['filter_sets' => [$set('and', $filter('commentable.created_at', 'equals', '2026-01-01'))]])))
            ->toThrow(ValidationException::class, 'This field cannot be filtered.');
    });

    it('refuses to sort by a column through it', function () {
        expect(fn () => (new ApiFilters(Comment::query()))->apply(filterableRequest(['sorts' => [['field' => 'commentable.created_at', 'sort_operator' => 'asc']]])))
            ->toThrow(ValidationException::class, 'This field cannot be sorted.');
    });
});

describe('sorts', function () {
    it('cannot truncate through a sort field', function () {
        Order::factory()->count(2)->create();

        expect(fn () => (new ApiFilters(Order::query()))->apply(filterableRequest(['sorts' => [['field' => 'truncate.id', 'sort_operator' => 'asc']]]))->get())
            ->toThrow(ValidationException::class)
            ->and(Order::query()->count())->toBe(2);
    });

    it('leaves the query unchanged when a sort is rejected', function () {
        $query = Order::query()->where('team_id', 1);
        $before = $query->toRawSql();

        try {
            (new ApiFilters($query))->apply(filterableRequest(['sorts' => [['field' => 'total', 'sort_operator' => 'desc'], ['field' => 'nope', 'sort_operator' => 'asc']]]));
        } catch (ValidationException) {
        }

        expect($query->toRawSql())->toBe($before);
    });
});
