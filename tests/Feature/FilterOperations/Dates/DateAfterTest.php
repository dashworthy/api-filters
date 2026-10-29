<?php

declare(strict_types=1);

use Dashworthy\ApiFilters\Enums\FilterOperator;
use Dashworthy\ApiFilters\FilterOperations\Dates\DateAfter;
use Workbench\App\Models\Order;

mutates(DateAfter::class);

beforeEach(fn () => Order::factory()->createMany([
    ['code' => 'day14', 'placed_at' => '2026-01-14 09:00:00'],
    ['code' => 'day15', 'placed_at' => '2026-01-15 13:45:00'],
    ['code' => 'day16', 'placed_at' => '2026-01-16 08:00:00'],
]));

it('excludes that day and earlier', function () {
    expect(codesMatching(new DateAfter, FilterOperator::DateAfter, 'placed_at', '2026-01-15'))->toBe(['day16']);
});
