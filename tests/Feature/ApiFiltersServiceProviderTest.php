<?php

declare(strict_types=1);

use Dashworthy\ApiFilters\ApiFiltersServiceProvider;
use Dashworthy\ApiFilters\Contracts\FilterOperationContract;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use Workbench\App\Http\Requests\ListOrdersRequest;
use Workbench\App\Models\Customer;
use Workbench\App\Models\Order;

mutates(ApiFiltersServiceProvider::class);

it('merges the package config', function () {
    expect(config('api-filters.limits.filter_sets'))->toBe(10)
        ->and(config('api-filters.operations'))->toHaveCount(17)->each->toImplement(FilterOperationContract::class);
});

it('keeps a host app value over the package default', function () {
    config()->set('api-filters.limits', ['filter_sets' => 3]);

    (new ApiFiltersServiceProvider(app()))->register();

    expect(config('api-filters.limits'))->toBe(['filter_sets' => 3]);
});

it('loads the package translations', function () {
    expect(trans('api-filters::validation.not_filterable'))->toBe('This field cannot be filtered.');
});

it('loads the translations and publishes nothing outside the console', function () {
    unset(ServiceProvider::$publishes[ApiFiltersServiceProvider::class]);
    (fn () => $this->isRunningInConsole = false)->call(app());
    app()->instance('translator', $translator = new Translator(new FileLoader(new Filesystem, app()->langPath()), 'en'));

    (new ApiFiltersServiceProvider(app()))->boot();

    expect($translator->get('api-filters::validation.not_filterable'))->toBe('This field cannot be filtered.')
        ->and(ServiceProvider::pathsToPublish(ApiFiltersServiceProvider::class))->toBe([]);
});

it('publishes the config file under the api-filters-config tag', function () {
    $paths = ServiceProvider::pathsToPublish(ApiFiltersServiceProvider::class, 'api-filters-config');

    expect(array_map(realpath(...), array_keys($paths)))->not->toContain(false)->toBe([realpath(__DIR__.'/../../config/api-filters.php')])
        ->and(array_values($paths))->toBe([config_path('api-filters.php')]);
});

it('publishes the translations under the api-filters-lang tag', function () {
    $paths = ServiceProvider::pathsToPublish(ApiFiltersServiceProvider::class, 'api-filters-lang');

    expect(array_map(realpath(...), array_keys($paths)))->not->toContain(false)->toBe([realpath(__DIR__.'/../../lang')])
        ->and(array_values($paths))->toBe([app()->langPath('vendor/api-filters')]);
});

it('publishes the config file and translations under the api-filters tag', function () {
    $paths = ServiceProvider::pathsToPublish(ApiFiltersServiceProvider::class, 'api-filters');

    expect(array_map(realpath(...), array_keys($paths)))->not->toContain(false)->toBe([
        realpath(__DIR__.'/../../config/api-filters.php'),
        realpath(__DIR__.'/../../lang'),
    ])->and(array_values($paths))->toBe([
        config_path('api-filters.php'),
        app()->langPath('vendor/api-filters'),
    ]);
});

it('filters through the builder macro', function () {
    Order::factory()->createMany([['code' => 'open', 'status' => 'open'], ['code' => 'closed', 'status' => 'closed']]);

    $orders = Order::query()->apiFilters([
        ['filter_set_operator' => 'and', 'filters' => [['field' => 'status', 'filter_operator' => 'equals', 'value' => 'open']]],
    ]);

    expect($orders->pluck('code')->all())->toBe(['open']);
});

it('filters through a relation with the macro', function () {
    Order::factory()->for(Customer::factory()->state(['name' => 'Ada']))->create(['code' => 'ada']);
    Order::factory()->for(Customer::factory()->state(['name' => 'Grace']))->create(['code' => 'grace']);

    $orders = Order::query()->apiFilters([
        ['filter_set_operator' => 'and', 'filters' => [['field' => 'customer.name', 'filter_operator' => 'equals', 'value' => 'Ada']]],
    ], allowedRelations: ['customer']);

    expect($orders->pluck('code')->all())->toBe(['ada']);
});

it('refuses an allowed relation path that is not a string', function () {
    Order::query()->apiFilters([], allowedRelations: [['customer']]);
})->throws(LogicException::class, 'Every allowed relation path must be a string.');

it('sorts through the builder macro', function () {
    Order::factory()->createMany([['code' => 'small', 'total' => 50], ['code' => 'large', 'total' => 500]]);

    expect(Order::query()->apiSorts([['field' => 'total', 'sort_operator' => 'desc']])->pluck('code')->all())->toBe(['large', 'small']);
});

it('sorts through a relation with the macro', function () {
    Order::factory()->for(Customer::factory()->state(['name' => 'Grace']))->create(['code' => 'grace']);
    Order::factory()->for(Customer::factory()->state(['name' => 'Ada']))->create(['code' => 'ada']);

    expect(Order::query()->apiSorts([['field' => 'customer.name', 'sort_operator' => 'asc']], allowedRelations: ['customer'])->pluck('code')->all())
        ->toBe(['ada', 'grace']);
});

describe('one request applies filters and sorts', function () {
    $request = function (array $input): ListOrdersRequest {
        $request = ListOrdersRequest::createFrom(Request::create('/orders', 'GET', $input));
        $request->setContainer(app())->setRedirector(app('redirect'))->validateResolved();

        return $request;
    };

    $input = [
        'filter_sets' => [['filter_set_operator' => 'and', 'filters' => [['field' => 'status', 'filter_operator' => 'equals', 'value' => 'open']]]],
        'sorts' => [['field' => 'total', 'sort_operator' => 'desc']],
    ];

    beforeEach(fn () => Order::factory()->createMany([
        ['code' => 'open-50', 'status' => 'open', 'total' => 50],
        ['code' => 'open-500', 'status' => 'open', 'total' => 500],
        ['code' => 'closed-900', 'status' => 'closed', 'total' => 900],
    ]));

    it('applies filters and sorts from one request', function () use ($request, $input) {
        expect(Order::query()->apiFiltersAndSorts($request($input))->pluck('code')->all())->toBe(['open-500', 'open-50']);
    });
});
