<?php

declare(strict_types=1);

use Dashworthy\ApiFilters\FilterOperations\Equality\LessThan;
use Workbench\App\Models\Order;

mutates(LessThan::class);

beforeEach(fn () => Order::factory()->createMany([
    ['code' => 'a50', 'total' => 50],
    ['code' => 'b100', 'total' => 100],
    ['code' => 'c500', 'total' => 500],
]));

it('excludes the boundary', function () {
    expect(codesMatching(new LessThan, 'lt', 'total', 100))->toBe(['a50']);
});
