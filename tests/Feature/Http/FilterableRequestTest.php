<?php

declare(strict_types=1);

use Dashworthy\ApiFilters\Http\FilterableRequest;
use Illuminate\Support\Facades\Route;
use Workbench\App\Http\Requests\ListOrdersRequest;
use Workbench\App\Http\Requests\ListOrdersWithoutRelationsRequest;
use Workbench\App\Models\Customer;
use Workbench\App\Models\Order;

mutates(FilterableRequest::class);

beforeEach(function () {
    Route::get('orders', fn (ListOrdersRequest $request) => Order::query()->apiFiltersAndSorts($request)->pluck('code'));
    Route::get('orders-without-relations', fn (ListOrdersWithoutRelationsRequest $request) => Order::query()->apiFiltersAndSorts($request)->pluck('code'));
    Route::get('validated', fn (ListOrdersRequest $request) => $request->validated());
});

$customerIsAda = ['filter_sets' => [['filter_set_operator' => 'and', 'filters' => [['field' => 'customer.name', 'filter_operator' => 'equals', 'value' => 'Ada']]]]];

$oneFilter = fn (string $field, string $operator, mixed $value): array => [
    'filter_sets' => [['filter_set_operator' => 'and', 'filters' => [['field' => $field, 'filter_operator' => $operator, 'value' => $value]]]],
];

describe('the request checks what the operations decide, before the controller runs', function () use ($oneFilter) {
    it('rejects an operator no operation handles', function () use ($oneFilter) {
        $this->json('GET', 'validated', $oneFilter('status', 'between', [1, 2]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['filter_sets.0.filters.0.filter_operator' => 'This filter operator is not supported.']);
    });

    it('checks the value against the rules of the operation that handles it', function () use ($oneFilter) {
        $this->json('GET', 'validated', $oneFilter('status', 'in', 'open'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['filter_sets.0.filters.0.value' => 'The value field must be a list.']);
    });

    it('normalizes each value before it checks it', function () use ($oneFilter) {
        $this->json('GET', 'validated', $oneFilter('notes', 'in', ['null', 'rush']))
            ->assertOk()
            ->assertJsonPath('filter_sets.0.filters.0.value', [null, 'rush']);
    });

    it('leaves the field to the engine, which knows the model', function () use ($oneFilter) {
        $this->json('GET', 'validated', $oneFilter('nope', 'equals', 'x'))->assertOk();

        $this->json('GET', 'orders', $oneFilter('nope', 'equals', 'x'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['filter_sets.0.filters.0.field' => 'This field cannot be filtered.']);
    });
});

it('responds 422 for a filter set without an operator', function () {
    $this->json('GET', 'orders', ['filter_sets' => [['filters' => [['field' => 'status', 'filter_operator' => 'equals', 'value' => 'open']]]]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['filter_sets.0.filter_set_operator']);
});

it('requires last with first', function () {
    $this->json('GET', 'orders', ['first' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['last']);
});

it('rejects a window larger than the limit', function () {
    $this->json('GET', 'orders', ['first' => 0, 'last' => 1001])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['last' => 'No more than 1000 rows may be requested at once.']);
});

it('rejects too many sorts with the package message', function () {
    $this->json('GET', 'orders', ['sorts' => array_fill(0, 6, ['field' => 'total', 'sort_operator' => 'asc'])])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sorts' => 'No more than 5 sorts may be sent.']);
});

it('denies relations by default', function () use ($customerIsAda) {
    $this->json('GET', 'orders-without-relations', $customerIsAda)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['filter_sets.0.filters.0.field']);
});

it('honours the request\'s allow-list', function () use ($customerIsAda) {
    Order::factory()->for(Customer::factory()->state(['name' => 'Ada']))->create(['code' => 'ada']);
    Order::factory()->for(Customer::factory()->state(['name' => 'Grace']))->create(['code' => 'grace']);

    $this->json('GET', 'orders', $customerIsAda)->assertOk()->assertExactJson(['ada']);
});
