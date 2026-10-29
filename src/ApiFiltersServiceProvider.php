<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters;

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
