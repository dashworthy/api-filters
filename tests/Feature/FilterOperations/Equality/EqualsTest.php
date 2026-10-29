<?php

declare(strict_types=1);

use Dashworthy\ApiFilters\Enums\FilterOperator;
use Dashworthy\ApiFilters\FilterOperations\Equality\Equals;
use Workbench\App\Models\Order;

mutates(Equals::class);

it('matches the value', function () {
    Order::factory()->createMany([['code' => 'open', 'status' => 'open'], ['code' => 'closed', 'status' => 'closed']]);

    expect(codesMatching(new Equals, FilterOperator::Equals, 'status', 'open'))->toBe(['open']);
});

it('matches null rows for null', function () {
    createOrdersWithNotes();

    expect(codesMatching(new Equals, FilterOperator::Equals, 'notes', null))->toBe(['none']);
});
