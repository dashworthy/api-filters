<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters;

use Dashworthy\ApiFilters\Http\FilterableRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Kirschbaum\PowerJoins\PowerJoinsServiceProvider;

class ApiFiltersServiceProvider extends ServiceProvider
{
    /**
     * Register any application services. Relation sorts use power joins'
     * builder methods, so its provider is registered here too, in case the
     * app turned off its package discovery; Laravel registers it only once.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/api-filters.php', 'api-filters');

        $this->app->register(PowerJoinsServiceProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'api-filters');

        Builder::macro('apiFiltersAndSorts', function (FilterableRequest $request): Builder {
            /** @var Builder<Model> $this */
            return (new ApiFilters($this))->apply($request);
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
