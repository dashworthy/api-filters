<?php

declare(strict_types=1);

use Dashworthy\ApiFilters\Enums\FilterOperator;
use Dashworthy\ApiFilters\FilterOperations\Dates\DateIsNot;
use Workbench\App\Models\Order;

mutates(DateIsNot::class);

beforeEach(fn () => Order::factory()->createMany([
    ['code' => 'day14', 'placed_at' => '2026-01-14 09:00:00'],
    ['code' => 'day15', 'placed_at' => '2026-01-15 13:45:00'],
    ['code' => 'day16', 'placed_at' => '2026-01-16 08:00:00'],
]));

it('excludes that day', function () {
    expect(codesMatching(new DateIsNot, FilterOperator::DateIsNot, 'placed_at', '2026-01-15'))->toBe(['day14', 'day16']);
});
