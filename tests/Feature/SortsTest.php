<?php

declare(strict_types=1);

use Dashworthy\ApiFilters\ApiFilters;
use Dashworthy\ApiFilters\Http\FilterableRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Workbench\App\Models\Country;
use Workbench\App\Models\Customer;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;

mutates(FilterableRequest::class, ApiFilters::class);

/*
 | $errors sorts a query on the model, an order unless named, and returns the
 | validation errors, keyed by input path. $codes sorts an orders query and
 | returns the orders' codes in that order. $sort builds the wire format.
 */
$errors = function (mixed $sorts, ?Model $model = null): array {
    try {
        (new ApiFilters(($model ?? new Order)->newQuery()))->apply(filterableRequest(['sorts' => $sorts]));
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
};

$codes = fn (array $sorts): array => (new ApiFilters(Order::query()))
    ->apply(filterableRequest(['sorts' => $sorts]))
    ->pluck('code')
    ->all();

$sort = fn (string $field, string $operator = 'asc'): array => ['field' => $field, 'sort_operator' => $operator];

it('sorts by the qualified column and the developer\'s path, spelled as the models list them', function () use ($sort) {
    expect((new ApiFilters(Order::query()))->apply(filterableRequest(['sorts' => [$sort('TOTAL', 'desc'), $sort('customer.NAME')]]))->toRawSql())
        ->toContain('order by `orders`.`total` desc, `customers`.`name` asc');
});

describe('sorts order the query', function () use ($codes, $sort) {
    it('orders by each sort in turn', function () use ($codes, $sort) {
        Order::factory()->createMany([['code' => 'one', 'total' => 500], ['code' => 'two', 'total' => 50], ['code' => 'three', 'total' => 500]]);

        expect($codes([$sort('total', 'desc'), $sort('code')]))->toBe(['one', 'three', 'two']);
    });

    it('skips a none sort', function () use ($codes, $sort) {
        Order::factory()->createMany([['code' => 'one', 'total' => 500], ['code' => 'two', 'total' => 50], ['code' => 'three', 'total' => 100]]);

        expect($codes([$sort('status', 'none'), $sort('total')]))->toBe(['two', 'three', 'one']);
    });

    it('sorts by a belongs to column', function () use ($codes, $sort) {
        Order::factory()->for(Customer::factory()->state(['name' => 'Grace']))->create(['code' => 'grace']);
        Order::factory()->for(Customer::factory()->state(['name' => 'Ada']))->create(['code' => 'ada']);

        expect($codes([$sort('customer.name')]))->toBe(['ada', 'grace']);
    });

    it('sorts through a nested to one path', function () use ($codes, $sort) {
        Order::factory()->for(Customer::factory()->for(Country::factory()->state(['name' => 'Zambia'])))->create(['code' => 'zambia']);
        Order::factory()->for(Customer::factory()->for(Country::factory()->state(['name' => 'Austria'])))->create(['code' => 'austria']);

        expect($codes([$sort('customer.country.name')]))->toBe(['austria', 'zambia']);
    });

    it('keeps rows without a related row', function () use ($codes, $sort) {
        Order::factory()->for(Customer::factory()->state(['name' => 'Grace']))->create(['code' => 'grace']);
        Order::factory()->create(['code' => 'nobody']);
        Order::factory()->for(Customer::factory()->state(['name' => 'Ada']))->create(['code' => 'ada']);

        expect($codes([$sort('customer.name')]))->toBe(['nobody', 'ada', 'grace']);
    });

    it('keeps each row\'s own columns when sorting through a relation', function () use ($sort) {
        Customer::factory()->count(3)->create();
        Order::factory()->for(Customer::factory()->state(['name' => 'Grace']))->create(['code' => 'grace']);
        Order::factory()->for(Customer::factory()->state(['name' => 'Ada']))->create(['code' => 'ada']);

        expect((new ApiFilters(Order::query()))->apply(filterableRequest(['sorts' => [$sort('customer.name')]]))->get()->pluck('code', 'id')->all())
            ->toEqual(Order::query()->pluck('code', 'id')->all());
    });
});

describe('only visible columns can be sorted', function () use ($errors, $sort) {
    it('rejects a hidden column', function () use ($errors, $sort) {
        expect(array_keys($errors([$sort('password')], new User)))->toBe(['sorts.0.field']);
    });

    it('uses one message for unknown and hidden columns', function () use ($errors, $sort) {
        expect($errors([$sort('password')], new User))->toBe(['sorts.0.field' => ['This field cannot be sorted.']])
            ->and($errors([$sort('nope')], new User))->toBe($errors([$sort('password')], new User));
    });

    it('rejects a relation the model does not list', function () use ($errors, $sort) {
        expect(array_keys($errors([$sort('country.name')], new Customer)))->toBe(['sorts.0.field']);
    });

    it('rejects a to many path', function () use ($errors, $sort) {
        expect($errors([$sort('lineItems.quantity')]))->toBe(['sorts.0.field' => ['This field cannot be sorted.']]);
    });

    it('rejects a hidden related column', function () use ($errors, $sort) {
        expect(array_keys($errors([$sort('customer.password')])))->toBe(['sorts.0.field']);
    });
});

describe('sorts are limited and validated', function () use ($errors, $sort) {
    it('rejects too many sorts', function () use ($errors, $sort) {
        expect($errors(array_fill(0, 6, $sort('total'))))->toBe(['sorts' => ['No more than 5 sorts may be sent.']])
            ->and($errors(array_fill(0, 5, $sort('total'))))->toBe([]);
    });

    it('rejects an unknown sort operator', function () use ($errors, $sort) {
        expect(array_keys($errors([$sort('total', 'up')])))->toBe(['sorts.0.sort_operator']);
    });

    it('requires a field', function () use ($errors) {
        expect(array_keys($errors([['sort_operator' => 'asc']])))->toBe(['sorts.0.field']);
    });

    it('requires sorts to be a list', function () use ($errors) {
        expect(array_keys($errors('total')))->toBe(['sorts']);
    });

    it('reads the limit from configuration', function () use ($errors, $sort) {
        config()->set('api-filters.limits.sorts', 1);

        expect(array_keys($errors([$sort('total'), $sort('code')])))->toBe(['sorts']);
    });
});
