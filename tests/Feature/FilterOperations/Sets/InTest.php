<?php

declare(strict_types=1);

use Dashworthy\ApiFilters\Data\FilterData;
use Dashworthy\ApiFilters\Enums\FilterOperator;
use Dashworthy\ApiFilters\FilterOperations\Equality\Equals;
use Dashworthy\ApiFilters\FilterOperations\Sets\In;
use Illuminate\Database\Eloquent\Builder;
use Workbench\App\Models\Order;

mutates(In::class);

it('matches any listed value', function () {
    Order::factory()->createMany([
        ['code' => 'open', 'status' => 'open'],
        ['code' => 'closed', 'status' => 'closed'],
        ['code' => 'void', 'status' => 'void'],
    ]);

    expect(codesMatching(new In, FilterOperator::In, 'status', ['open', 'closed']))->toBe(['closed', 'open']);
});

it('also matches null rows when the list holds null', function () {
    createOrdersWithNotes();

    expect(codesMatching(new In, FilterOperator::In, 'notes', ['rush', null]))->toBe(['none', 'rush'])
        ->and(codesMatching(new In, FilterOperator::In, 'notes', [null]))->toBe(['none']);
});

it('keeps its or inside the filter', function () {
    Order::factory()->createMany([
        ['code' => 'open-rush', 'status' => 'open', 'notes' => 'rush'],
        ['code' => 'closed-none', 'status' => 'closed', 'notes' => null],
    ]);
    $query = Order::query();

    $query->where(function (Builder $group) {
        (new Equals)->handle($group, new FilterData('orders.status', FilterOperator::Equals->value, 'open'));
        (new In)->handle($group, new FilterData('orders.notes', FilterOperator::In->value, ['rush', null]));
    });

    expect($query->pluck('code')->all())->toBe(['open-rush']);
});
