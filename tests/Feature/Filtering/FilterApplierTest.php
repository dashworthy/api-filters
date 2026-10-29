<?php

declare(strict_types=1);

use Dashworthy\ApiFilters\Adapters\MariaDbAdapter;
use Dashworthy\ApiFilters\Contracts\FilterOperationContract;
use Dashworthy\ApiFilters\Data\FilterData;
use Dashworthy\ApiFilters\Enums\FilterOperator;
use Dashworthy\ApiFilters\Enums\FilterSetOperator;
use Dashworthy\ApiFilters\Filtering\FilterApplier;
use Dashworthy\ApiFilters\FilterOperations\Dates\DateAfter;
use Dashworthy\ApiFilters\FilterOperations\Dates\DateBefore;
use Dashworthy\ApiFilters\FilterOperations\Dates\DateIs;
use Dashworthy\ApiFilters\FilterOperations\Dates\DateIsNot;
use Dashworthy\ApiFilters\FilterOperations\Equality\DoesNotEqual;
use Dashworthy\ApiFilters\FilterOperations\Equality\Equals;
use Dashworthy\ApiFilters\FilterOperations\Equality\GreaterThan;
use Dashworthy\ApiFilters\FilterOperations\Equality\GreaterThanOrEqualTo;
use Dashworthy\ApiFilters\FilterOperations\Equality\LessThan;
use Dashworthy\ApiFilters\FilterOperations\Equality\LessThanOrEqualTo;
use Dashworthy\ApiFilters\FilterOperations\Sets\In;
use Dashworthy\ApiFilters\FilterOperations\Sets\NotIn;
use Dashworthy\ApiFilters\FilterOperations\Text\Contains;
use Dashworthy\ApiFilters\FilterOperations\Text\DoesNotContain;
use Dashworthy\ApiFilters\FilterOperations\Text\EndsWith;
use Dashworthy\ApiFilters\FilterOperations\Text\StartsWith;
use Illuminate\Database\Eloquent\Builder;
use Workbench\App\Models\Order;

mutates(FilterApplier::class);

it('hands each operator to its operation', function (FilterOperator $operator, FilterOperationContract $operation, mixed $value) {
    $filterData = FilterData::fromValidated(['column' => 'orders.notes', 'filter_operator' => $operator->value, 'value' => $value]);
    $applied = Order::query()->where(fn (Builder $group) => (new FilterApplier(new MariaDbAdapter))->apply($group, $filterData, FilterSetOperator::And));
    $handled = Order::query()->where(fn (Builder $group) => $operation->handle($group, $filterData, FilterSetOperator::And, new MariaDbAdapter));

    expect($applied->toRawSql())->toBe($handled->toRawSql());
})->with([
    'equals' => [FilterOperator::Equals, new Equals, 'rush'],
    'doesNotEquals' => [FilterOperator::DoesNotEquals, new DoesNotEqual, 'rush'],
    'lt' => [FilterOperator::Lt, new LessThan, 'rush'],
    'lte' => [FilterOperator::Lte, new LessThanOrEqualTo, 'rush'],
    'gt' => [FilterOperator::Gt, new GreaterThan, 'rush'],
    'gte' => [FilterOperator::Gte, new GreaterThanOrEqualTo, 'rush'],
    'in' => [FilterOperator::In, new In, ['rush', null]],
    'notIn' => [FilterOperator::NotIn, new NotIn, ['rush', null]],
    'startsWith' => [FilterOperator::StartsWith, new StartsWith, 'ru'],
    'contains' => [FilterOperator::Contains, new Contains, 'us'],
    'endsWith' => [FilterOperator::EndsWith, new EndsWith, 'sh'],
    'doesNotContain' => [FilterOperator::DoesNotContain, new DoesNotContain, 'us'],
    'dateIs' => [FilterOperator::DateIs, new DateIs, '2026-01-15'],
    'dateIsNot' => [FilterOperator::DateIsNot, new DateIsNot, '2026-01-15'],
    'dateBefore' => [FilterOperator::DateBefore, new DateBefore, '2026-01-15'],
    'dateAfter' => [FilterOperator::DateAfter, new DateAfter, '2026-01-15'],
]);

it('joins each filter with its set operator', function () {
    Order::factory()->createMany([
        ['code' => 'open', 'status' => 'open', 'total' => 50],
        ['code' => 'closed', 'status' => 'closed', 'total' => 500],
        ['code' => 'void', 'status' => 'void', 'total' => 50],
    ]);
    $applier = new FilterApplier(new MariaDbAdapter);
    $query = Order::query();

    $query->where(function (Builder $group) use ($applier) {
        $applier->apply($group, new FilterData('orders.status', FilterOperator::Equals, 'open'), FilterSetOperator::Or);
        $applier->apply($group, new FilterData('orders.total', FilterOperator::Gt, 100), FilterSetOperator::Or);
    });

    expect($query->orderBy('code')->pluck('code')->all())->toBe(['closed', 'open']);
});
