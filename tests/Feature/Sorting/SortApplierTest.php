<?php

declare(strict_types=1);

use Dashworthy\ApiFilters\Data\SortData;
use Dashworthy\ApiFilters\Enums\SortOperator;
use Dashworthy\ApiFilters\Sorting\SortApplier;
use Workbench\App\Models\Country;
use Workbench\App\Models\Customer;
use Workbench\App\Models\Order;

mutates(SortApplier::class);

/*
 | $codes sorts an orders query by each sort in turn and returns the orders'
 | codes in that order.
 */
$codes = function (SortData ...$sorts): array {
    $query = Order::query();

    foreach ($sorts as $sortData) {
        (new SortApplier)->apply($query, $sortData);
    }

    return $query->pluck('code')->all();
};

it('orders by each sort in turn', function () use ($codes) {
    Order::factory()->createMany([['code' => 'one', 'total' => 500], ['code' => 'two', 'total' => 50], ['code' => 'three', 'total' => 500]]);

    expect($codes(new SortData('orders.total', SortOperator::Desc), new SortData('orders.id', SortOperator::Asc)))
        ->toBe(['one', 'three', 'two']);
});

it('skips a none sort', function () use ($codes) {
    Order::factory()->createMany([['code' => 'one', 'total' => 500], ['code' => 'two', 'total' => 50], ['code' => 'three', 'total' => 100]]);

    expect($codes(new SortData('orders.status', SortOperator::None), new SortData('orders.total', SortOperator::Asc)))
        ->toBe(['two', 'three', 'one']);
});

it('sorts by a belongs to column', function () use ($codes) {
    Order::factory()->for(Customer::factory()->state(['name' => 'Grace']))->create(['code' => 'grace']);
    Order::factory()->for(Customer::factory()->state(['name' => 'Ada']))->create(['code' => 'ada']);

    expect($codes(new SortData('name', SortOperator::Asc, 'customer')))->toBe(['ada', 'grace']);
});

it('sorts through a nested to one path', function () use ($codes) {
    Order::factory()->for(Customer::factory()->for(Country::factory()->state(['name' => 'Zambia'])))->create(['code' => 'zambia']);
    Order::factory()->for(Customer::factory()->for(Country::factory()->state(['name' => 'Austria'])))->create(['code' => 'austria']);

    expect($codes(new SortData('name', SortOperator::Asc, 'customer.country')))->toBe(['austria', 'zambia']);
});

it('keeps rows without a related row', function () use ($codes) {
    Order::factory()->for(Customer::factory()->state(['name' => 'Grace']))->create(['code' => 'grace']);
    Order::factory()->create(['code' => 'nobody']);
    Order::factory()->for(Customer::factory()->state(['name' => 'Ada']))->create(['code' => 'ada']);

    expect($codes(new SortData('name', SortOperator::Asc, 'customer')))->toBe(['nobody', 'ada', 'grace']);
});

it('keeps each row\'s own columns when sorting through a relation', function () {
    Customer::factory()->count(3)->create();
    Order::factory()->for(Customer::factory()->state(['name' => 'Grace']))->create(['code' => 'grace']);
    Order::factory()->for(Customer::factory()->state(['name' => 'Ada']))->create(['code' => 'ada']);
    $query = Order::query();

    (new SortApplier)->apply($query, new SortData('name', SortOperator::Asc, 'customer'));

    expect($query->get()->pluck('code', 'id')->all())->toEqual(Order::query()->pluck('code', 'id')->all());
});
