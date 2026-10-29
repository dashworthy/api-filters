<?php

declare(strict_types=1);

use Dashworthy\ApiFilters\Adapters\MariaDbAdapter;
use Workbench\App\Models\Order;

mutates(MariaDbAdapter::class);

beforeEach(fn () => Order::factory()->createMany([
    ['code' => 'a', 'total' => 50, 'notes' => 'Rush', 'placed_at' => '2026-01-14 09:00:00'],
    ['code' => 'b', 'total' => 100, 'notes' => 'gift', 'placed_at' => '2026-01-15 13:45:00'],
]));

it('compares a column with a value', function () {
    expect((new MariaDbAdapter)->compare(Order::query(), 'orders.total', '>', 50, 'and')->pluck('code')->all())->toBe(['b']);
});

it('matches a list with in or not in', function () {
    $adapter = new MariaDbAdapter;

    expect($adapter->whereIn(Order::query(), 'orders.total', [50, 75], 'and')->pluck('code')->all())->toBe(['a'])
        ->and($adapter->whereIn(Order::query(), 'orders.total', [50, 75], 'and', not: true)->pluck('code')->all())->toBe(['b']);
});

it('matches a pattern in any letter case, or its opposite', function () {
    $adapter = new MariaDbAdapter;

    expect($adapter->whereLike(Order::query(), 'orders.notes', 'rU%', 'and')->pluck('code')->all())->toBe(['a'])
        ->and($adapter->whereLike(Order::query(), 'orders.notes', 'rU%', 'and', not: true)->pluck('code')->all())->toBe(['b']);
});

it('compares the calendar date of a column', function () {
    expect((new MariaDbAdapter)->whereDate(Order::query(), 'orders.placed_at', '=', '2026-01-15', 'and')->pluck('code')->all())->toBe(['b']);
});

it('joins each condition with the boolean it is given', function () {
    $adapter = new MariaDbAdapter;
    $query = Order::query()->where('orders.total', 0);

    $adapter->compare($query, 'orders.total', '=', 50, 'or');
    $adapter->whereIn($query, 'orders.total', [100], 'or');
    $adapter->whereLike($query, 'orders.notes', 'nothing', 'or');
    $adapter->whereDate($query, 'orders.placed_at', '=', '2026-01-01', 'or');

    expect($query->orderBy('code')->pluck('code')->all())->toBe(['a', 'b'])
        ->and(collect($query->getQuery()->wheres)->pluck('boolean')->all())->toBe(['and', 'or', 'or', 'or', 'or']);
});
