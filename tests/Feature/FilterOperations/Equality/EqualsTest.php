<?php

declare(strict_types=1);

use Dashworthy\ApiFilters\FilterOperations\Equality\Equals;
use Workbench\App\Models\Order;

mutates(Equals::class);

it('matches the value', function () {
    Order::factory()->createMany([['code' => 'open', 'status' => 'open'], ['code' => 'closed', 'status' => 'closed']]);

    expect(codesMatching(new Equals, 'equals', 'status', 'open'))->toBe(['open']);
});

it('matches null rows for null', function () {
    createOrdersWithNotes();

    expect(codesMatching(new Equals, 'equals', 'notes', null))->toBe(['none']);
});
