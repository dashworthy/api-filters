<?php

declare(strict_types=1);

use Dashworthy\ApiFilters\Adapters\MariaDbAdapter;
use Dashworthy\ApiFilters\Data\FilterData;
use Dashworthy\ApiFilters\Enums\FilterOperator;
use Dashworthy\ApiFilters\Enums\FilterSetOperator;
use Dashworthy\ApiFilters\FilterOperations\Equality\Equals;
use Dashworthy\ApiFilters\FilterOperations\Text\DoesNotContain;
use Illuminate\Database\Eloquent\Builder;
use Workbench\App\Models\Order;

mutates(DoesNotContain::class);

it('includes null rows', function () {
    createOrdersWithNotes();

    expect(codesMatching(new DoesNotContain, FilterOperator::DoesNotContain, 'notes', 'US'))->toBe(['gift', 'none']);
});

it('keeps its or inside the filter', function () {
    Order::factory()->createMany([
        ['code' => 'open-gift', 'status' => 'open', 'notes' => 'gift'],
        ['code' => 'closed-none', 'status' => 'closed', 'notes' => null],
    ]);
    $query = Order::query();

    $query->where(function (Builder $group) {
        (new Equals)->handle($group, new FilterData('orders.status', FilterOperator::Equals, 'open'), FilterSetOperator::And, new MariaDbAdapter);
        (new DoesNotContain)->handle($group, new FilterData('orders.notes', FilterOperator::DoesNotContain, 'rush'), FilterSetOperator::And, new MariaDbAdapter);
    });

    expect($query->pluck('code')->all())->toBe(['open-gift']);
});
