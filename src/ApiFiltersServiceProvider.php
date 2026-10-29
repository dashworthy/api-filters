<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class ApiFiltersServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/api-filters.php', 'api-filters');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'api-filters');

        Builder::macro('apiFilters', function (mixed $filterSets): Builder {
            /** @var Builder<Model> $this */
            return ApiFilters::for($this)->filter($filterSets)->query();
        });

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/api-filters.php' => config_path('api-filters.php'),
        ], ['api-filters', 'api-filters-config']);

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/api-filters'),
        ], ['api-filters', 'api-filters-lang']);
    }
}
