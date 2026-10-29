<?php

declare(strict_types=1);

use Dashworthy\ApiFilters\ApiFiltersServiceProvider;
use Illuminate\Support\ServiceProvider;

mutates(ApiFiltersServiceProvider::class);

it('merges the package config', function () {
    expect(config('api-filters.placeholder'))->toBe('default');
});

it('loads the package translations', function () {
    expect(trans('api-filters::messages.placeholder'))->toBe('ApiFilters placeholder translation.');
});

it('publishes the config file under the api-filters-config tag', function () {
    $paths = ServiceProvider::pathsToPublish(ApiFiltersServiceProvider::class, 'api-filters-config');

    expect(array_map(realpath(...), array_keys($paths)))->toBe([realpath(__DIR__.'/../../config/api-filters.php')])
        ->and(array_values($paths))->toBe([config_path('api-filters.php')]);
});

it('publishes the translations under the api-filters-lang tag', function () {
    $paths = ServiceProvider::pathsToPublish(ApiFiltersServiceProvider::class, 'api-filters-lang');

    expect(array_map(realpath(...), array_keys($paths)))->toBe([realpath(__DIR__.'/../../lang')])
        ->and(array_values($paths))->toBe([app()->langPath('vendor/api-filters')]);
});

it('publishes the config file and translations under the api-filters tag', function () {
    $paths = ServiceProvider::pathsToPublish(ApiFiltersServiceProvider::class, 'api-filters');

    expect(array_map(realpath(...), array_keys($paths)))->toBe([
        realpath(__DIR__.'/../../config/api-filters.php'),
        realpath(__DIR__.'/../../lang'),
    ])->and(array_values($paths))->toBe([
        config_path('api-filters.php'),
        app()->langPath('vendor/api-filters'),
    ]);
});
