<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters;

use Dashworthy\ApiFilters\Http\FilterableRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Kirschbaum\PowerJoins\PowerJoinsServiceProvider;
use LogicException;

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

        $paths = fn (array $paths): array => array_map(
            fn (mixed $path): string => is_string($path) ? $path : throw new LogicException('Every allowed relation path must be a string.'),
            array_values($paths),
        );

        Builder::macro('apiFilters', function (mixed $filterSets, array $allowedRelations = []) use ($paths): Builder {
            /** @var Builder<Model> $this */
            return ApiFilters::for($this)->allowedRelations($paths($allowedRelations))->filter($filterSets)->query();
        });

        Builder::macro('apiSorts', function (mixed $sorts, array $allowedRelations = []) use ($paths): Builder {
            /** @var Builder<Model> $this */
            return ApiFilters::for($this)->allowedRelations($paths($allowedRelations))->sort($sorts)->query();
        });

        Builder::macro('apiFiltersAndSorts', function (FilterableRequest $request): Builder {
            /** @var Builder<Model> $this */
            return ApiFilters::for($this)
                ->allowedRelations($request->allowedRelations())
                ->filter($request->validated('filter_sets', []))
                ->sort($request->validated('sorts', []))
                ->query();
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
