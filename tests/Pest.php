<?php

declare(strict_types=1);

use Dashworthy\ApiFilters\Adapters\MariaDbAdapter;
use Dashworthy\ApiFilters\Contracts\FilterOperationContract;
use Dashworthy\ApiFilters\Data\FilterData;
use Dashworthy\ApiFilters\Enums\FilterOperator;
use Dashworthy\ApiFilters\Enums\FilterSetOperator;
use Dashworthy\ApiFilters\Filtering\FilterApplier;
use Dashworthy\ApiFilters\Tests\TestCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Workbench\App\Models\Order;

uses(TestCase::class)->in(__DIR__);

uses(RefreshDatabase::class)->in('Feature');

/**
 * Apply one filter operation to an orders query, inside a filter set's group,
 * and return the codes of the orders it matches.
 *
 * @return list<string>
 */
function codesMatching(FilterOperationContract $operation, FilterOperator $operator, string $column, mixed $value): array
{
    $query = Order::query();
    $filterData = new FilterData($query->qualifyColumn($column), $operator->value, $value);

    $query->where(fn (Builder $group) => $operation->handle($group, $filterData, FilterSetOperator::And, new MariaDbAdapter));

    return $query->orderBy('code')->pluck('code')->all();
}

/**
 * The applier ApiFilters builds for a MariaDB query, with the configured operations.
 */
function filterApplier(): FilterApplier
{
    return new FilterApplier(new MariaDbAdapter, array_map(app(...), config('api-filters.operations')));
}

/**
 * Orders coded rush, gift and none, whose notes are rush, gift and NULL.
 */
function createOrdersWithNotes(): void
{
    Order::factory()->createMany([
        ['code' => 'rush', 'notes' => 'rush'],
        ['code' => 'gift', 'notes' => 'gift'],
        ['code' => 'none', 'notes' => null],
    ]);
}
