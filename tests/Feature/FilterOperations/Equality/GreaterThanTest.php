<?php

declare(strict_types=1);

use Dashworthy\ApiFilters\Enums\FilterOperator;
use Dashworthy\ApiFilters\FilterOperations\Equality\GreaterThan;
use Workbench\App\Models\Order;

mutates(GreaterThan::class);

beforeEach(fn () => Order::factory()->createMany([
    ['code' => 'a50', 'total' => 50],
    ['code' => 'b100', 'total' => 100],
    ['code' => 'c500', 'total' => 500],
]));

it('excludes the boundary', function () {
    expect(codesMatching(new GreaterThan, FilterOperator::Gt, 'total', 100))->toBe(['c500']);
});
