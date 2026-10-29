<?php

declare(strict_types=1);

use Dashworthy\ApiFilters\Adapters\MariaDbAdapter;
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

it('finds the built-in operation for each operator', function (FilterOperator $operator, string $operation) {
    expect(filterApplier()->operationFor(new FilterData('orders.notes', $operator->value)))->toBeInstanceOf($operation);
})->with([
    'equals' => [FilterOperator::Equals, Equals::class],
    'doesNotEquals' => [FilterOperator::DoesNotEquals, DoesNotEqual::class],
    'lt' => [FilterOperator::Lt, LessThan::class],
    'lte' => [FilterOperator::Lte, LessThanOrEqualTo::class],
    'gt' => [FilterOperator::Gt, GreaterThan::class],
    'gte' => [FilterOperator::Gte, GreaterThanOrEqualTo::class],
    'in' => [FilterOperator::In, In::class],
    'notIn' => [FilterOperator::NotIn, NotIn::class],
    'startsWith' => [FilterOperator::StartsWith, StartsWith::class],
    'contains' => [FilterOperator::Contains, Contains::class],
    'endsWith' => [FilterOperator::EndsWith, EndsWith::class],
    'doesNotContain' => [FilterOperator::DoesNotContain, DoesNotContain::class],
    'dateIs' => [FilterOperator::DateIs, DateIs::class],
    'dateIsNot' => [FilterOperator::DateIsNot, DateIsNot::class],
    'dateBefore' => [FilterOperator::DateBefore, DateBefore::class],
    'dateAfter' => [FilterOperator::DateAfter, DateAfter::class],
]);

it('finds no operation for an operator none can handle', function () {
    expect(filterApplier()->operationFor(new FilterData('orders.total', 'between', [1, 2])))->toBeNull();
});

it('asks the operations in order', function () {
    $first = new Equals;

    expect((new FilterApplier(new MariaDbAdapter, [new In, $first, new Equals]))->operationFor(new FilterData('orders.status', 'equals', 'open')))
        ->toBe($first);
});

it('hands the filter to its operation', function () {
    $filterData = new FilterData('orders.notes', 'in', ['rush', null]);
    $applied = Order::query()->where(fn (Builder $group) => filterApplier()->apply($group, $filterData, FilterSetOperator::And));
    $handled = Order::query()->where(fn (Builder $group) => (new In)->handle($group, $filterData, FilterSetOperator::And, new MariaDbAdapter));

    expect($applied->toRawSql())->toBe($handled->toRawSql());
});

it('refuses a filter no operation can handle', function () {
    filterApplier()->apply(Order::query(), new FilterData('orders.total', 'between', [1, 2]), FilterSetOperator::And);
})->throws(LogicException::class, 'No api-filters operation can handle the [between] operator.');

it('joins each filter with its set operator', function () {
    Order::factory()->createMany([
        ['code' => 'open', 'status' => 'open', 'total' => 50],
        ['code' => 'closed', 'status' => 'closed', 'total' => 500],
        ['code' => 'void', 'status' => 'void', 'total' => 50],
    ]);
    $applier = filterApplier();
    $query = Order::query();

    $query->where(function (Builder $group) use ($applier) {
        $applier->apply($group, new FilterData('orders.status', 'equals', 'open'), FilterSetOperator::Or);
        $applier->apply($group, new FilterData('orders.total', 'gt', 100), FilterSetOperator::Or);
    });

    expect($query->orderBy('code')->pluck('code')->all())->toBe(['closed', 'open']);
});
