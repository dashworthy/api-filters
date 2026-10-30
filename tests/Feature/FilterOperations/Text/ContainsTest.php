<?php

declare(strict_types=1);

use Dashworthy\ApiFilters\FilterOperations\Text\Contains;
use Workbench\App\Models\Order;

mutates(Contains::class);

it('matches a substring in any letter case', function () {
    createOrdersWithNotes();

    expect(codesMatching(new Contains, 'contains', 'notes', 'IF'))->toBe(['gift']);
});

it('reads a percent sign or an underscore as a wildcard', function () {
    Order::factory()->createMany([['code' => 'off', 'notes' => '50% off'], ['code' => 'cents', 'notes' => '50 cents']]);

    expect(codesMatching(new Contains, 'contains', 'notes', '50%'))->toBe(['cents', 'off'])
        ->and(codesMatching(new Contains, 'contains', 'notes', '5_ c'))->toBe(['cents']);
});
