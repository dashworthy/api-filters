<?php

declare(strict_types=1);

use Dashworthy\ApiFilters\Adapters\MariaDbAdapter;
use Dashworthy\ApiFilters\ApiFiltersServiceProvider;
use Dashworthy\ApiFilters\Contracts\FilterOperationContract;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use Workbench\App\Models\Order;

mutates(ApiFiltersServiceProvider::class);

it('merges the package config', function () {
    expect(config('api-filters.limits.filter_sets'))->toBe(10)
        ->and(config('api-filters.adapters'))->toBe(['mariadb' => MariaDbAdapter::class])
        ->and(config('api-filters.operations'))->toHaveCount(16)->each->toImplement(FilterOperationContract::class);
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
