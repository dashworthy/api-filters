<?php

declare(strict_types=1);

use Dashworthy\ApiFilters\ApiFilters;
use Dashworthy\ApiFilters\Contracts\DatabaseAdapterContract;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Workbench\App\Models\Order;

mutates(ApiFilters::class);

/*
 | $codes filters an orders query and returns the matching orders' codes.
 | $set and $filter build the wire format.
 */
$codes = fn (array $filterSets, ?Builder $query = null): array => ApiFilters::for($query ?? Order::query())
    ->filter($filterSets)
    ->query()
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
            ApiFilters::for($query)->filter([$set('and', $filter('status', 'equals', 'open'), $filter('nope', 'equals', 'x'))]);
        } catch (ValidationException) {
        }

        expect($query->toRawSql())->toBe($before);
    });
});

describe('relation paths are denied by default', function () use ($filter, $set) {
    it('cannot truncate through a relation name', function () use ($filter, $set) {
        Order::factory()->count(2)->create();

        expect(fn () => ApiFilters::for(Order::query())->filter([$set('and', $filter('truncate.id', 'equals', 1))])->query()->get())
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

    it('can switch normalizers off', function () use ($codes, $filter, $set) {
        config()->set('api-filters.normalizers', []);
        Order::factory()->createMany([['code' => 'text', 'notes' => 'null'], ['code' => 'none', 'notes' => null]]);

        expect($codes([$set('and', $filter('notes', 'equals', 'null'))]))->toBe(['text']);
    });
});

describe('database adapters', function () use ($filter, $set) {
    it('writes the SQL through the adapter mapped to the connection driver', function () use ($filter, $set) {
        config()->set('api-filters.adapters.mariadb', new class implements DatabaseAdapterContract
        {
            public function compare(Builder $query, string $column, string $operator, string|int|float|bool|null $value, string $boolean): Builder
            {
                throw new RuntimeException("compare {$column} {$operator} {$value}");
            }

            public function whereIn(Builder $query, string $column, array $values, string $boolean, bool $not = false): Builder
            {
                return $query;
            }

            public function whereLike(Builder $query, string $column, string $pattern, string $boolean, bool $not = false): Builder
            {
                return $query;
            }

            public function whereDate(Builder $query, string $column, string $operator, string $date, string $boolean): Builder
            {
                return $query;
            }
        });

        ApiFilters::for(Order::query())->filter([$set('and', $filter('total', 'gt', 100))]);
    })->throws(RuntimeException::class, 'compare orders.total > 100');

    it('refuses a connection whose driver has no adapter', function () use ($filter, $set) {
        ApiFilters::for(Order::on('testing'))->filter([$set('and', $filter('status', 'equals', 'open'))]);
    })->throws(LogicException::class, 'No api-filters adapter is registered for the [sqlite] database driver.');

    it('resolves an adapter class from the container', function () use ($filter, $set) {
        config()->set('api-filters.adapters.sqlite', 'not-a-class');

        ApiFilters::for(Order::on('testing'))->filter([$set('and', $filter('status', 'equals', 'open'))]);
    })->throws(BindingResolutionException::class);
});
