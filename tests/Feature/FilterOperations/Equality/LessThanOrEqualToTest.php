<?php

declare(strict_types=1);

use Dashworthy\ApiFilters\Enums\FilterOperator;
use Dashworthy\ApiFilters\FilterOperations\Equality\LessThanOrEqualTo;
use Workbench\App\Models\Order;

mutates(LessThanOrEqualTo::class);

beforeEach(fn () => Order::factory()->createMany([
    ['code' => 'a50', 'total' => 50],
    ['code' => 'b100', 'total' => 100],
    ['code' => 'c500', 'total' => 500],
]));

it('includes the boundary', function () {
    expect(codesMatching(new LessThanOrEqualTo, FilterOperator::Lte, 'total', 100))->toBe(['a50', 'b100']);
});
