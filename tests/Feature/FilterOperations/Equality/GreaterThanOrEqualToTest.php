<?php

declare(strict_types=1);

use Dashworthy\ApiFilters\Enums\FilterOperator;
use Dashworthy\ApiFilters\FilterOperations\Equality\GreaterThanOrEqualTo;
use Workbench\App\Models\Order;

mutates(GreaterThanOrEqualTo::class);

beforeEach(fn () => Order::factory()->createMany([
    ['code' => 'a50', 'total' => 50],
    ['code' => 'b100', 'total' => 100],
    ['code' => 'c500', 'total' => 500],
]));

it('includes the boundary', function () {
    expect(codesMatching(new GreaterThanOrEqualTo, FilterOperator::Gte, 'total', 100))->toBe(['b100', 'c500']);
});
