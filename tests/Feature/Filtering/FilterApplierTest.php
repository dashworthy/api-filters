<?php

declare(strict_types=1);

use Dashworthy\ApiFilters\ApiFilters;
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
use Dashworthy\ApiFilters\FilterOperations\Relations\DoesntHave;
use Dashworthy\ApiFilters\FilterOperations\Sets\In;
use Dashworthy\ApiFilters\FilterOperations\Sets\NotIn;
use Dashworthy\ApiFilters\FilterOperations\Text\Contains;
use Dashworthy\ApiFilters\FilterOperations\Text\DoesNotContain;
use Dashworthy\ApiFilters\FilterOperations\Text\EndsWith;
use Dashworthy\ApiFilters\FilterOperations\Text\StartsWith;
use Illuminate\Database\Eloquent\Builder;
use Workbench\App\Models\Customer;
use Workbench\App\Models\LineItem;
use Workbench\App\Models\Order;
use Workbench\App\Models\Product;

mutates(FilterApplier::class);

/*
 | $codes filters an orders query through the engine and returns the matching
 | orders' codes. $filter builds the wire format.
 */
$codes = fn (string $setOperator, array $filters, ?Builder $query = null): array => (new ApiFilters($query ?? Order::query()))
    ->apply(filterableRequest(['filter_sets' => [['filter_set_operator' => $setOperator, 'filters' => $filters]]]))
    ->orderBy('code')
    ->pluck('code')
    ->all();

$filter = fn (string $field, string $operator, mixed $value): array => ['field' => $field, 'filter_operator' => $operator, 'value' => $value];

/*
 | $either is an operation that ORs two values of a column, to show an OR in an
 | operation's condition stays within its filter.
 */
$either = new class implements FilterOperationContract
{
    public function canHandle(FilterData $filterData): bool
    {
        return $filterData->operator === 'either';
    }

    public function rules(): array
    {
        return [];
    }

    public function handle(Builder $query, FilterData $filterData): Builder
    {
        [$first, $second] = $filterData->listValue();

        return $query->where($filterData->column, $first)->orWhere($filterData->column, $second);
    }
};

it('finds the built-in operation for each operator', function (FilterOperator $operator, string $operation) {
    expect(filterApplier()->operationsFor(new FilterData('orders.notes', $operator->value)))
        ->toHaveCount(1)
        ->sequence(fn ($found) => $found->toBeInstanceOf($operation));
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
    'doesntHave' => [FilterOperator::DoesntHave, DoesntHave::class],
]);

it('finds no operation for an operator none can handle', function () {
    expect(filterApplier()->operationsFor(new FilterData('orders.total', 'between', [1, 2])))->toBe([]);
});

it('finds every operation that can handle a filter, in order', function () {
    $first = new Equals;
    $second = new Equals;

    expect((new FilterApplier([new In, $first, $second]))->operationsFor(new FilterData('orders.status', 'equals', 'open')))
        ->toBe([$first, $second]);
});

it('applies every operation that can handle a filter', function () {
    Order::factory()->createMany([
        ['code' => 'open-rush', 'status' => 'open', 'notes' => 'rush'],
        ['code' => 'closed-rush', 'status' => 'closed', 'notes' => 'rush'],
        ['code' => 'closed-gift', 'status' => 'closed', 'notes' => 'gift'],
    ]);
    $closedOnly = new class implements FilterOperationContract
    {
        public function canHandle(FilterData $filterData): bool
        {
            return $filterData->operator === 'equals';
        }

        public function rules(): array
        {
            return [];
        }

        public function handle(Builder $query, FilterData $filterData): Builder
        {
            return $query->where('orders.status', 'closed');
        }
    };
    $query = Order::query();

    $query->where(fn (Builder $group) => (new FilterApplier([new Equals, $closedOnly]))->apply(
        $group,
        new FilterData('orders.notes', 'equals', 'rush'),
        FilterSetOperator::And,
    ));

    expect($query->pluck('code')->all())->toBe(['closed-rush']);
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

it('keeps an or in an operation\'s condition within its filter', function () use ($either) {
    Order::factory()->createMany([
        ['code' => 'open-rush', 'status' => 'open', 'notes' => 'rush'],
        ['code' => 'closed-gift', 'status' => 'closed', 'notes' => 'gift'],
    ]);
    $applier = new FilterApplier([new Equals, $either]);
    $query = Order::query();

    $query->where(function (Builder $group) use ($applier) {
        $applier->apply($group, new FilterData('orders.status', 'equals', 'open'), FilterSetOperator::And);
        $applier->apply($group, new FilterData('orders.notes', 'either', ['rush', 'gift']), FilterSetOperator::And);
    });

    expect($query->pluck('code')->all())->toBe(['open-rush']);
});

describe('relations', function () use ($codes, $filter) {
    it('filters through an allow-listed relation', function () use ($codes, $filter) {
        Order::factory()->for(Customer::factory()->state(['name' => 'Ada']))->create(['code' => 'ada']);
        Order::factory()->for(Customer::factory()->state(['name' => 'Grace']))->create(['code' => 'grace']);

        expect($codes('and', [$filter('customer.name', 'equals', 'Ada')]))->toBe(['ada']);
    });

    it('filters through a nested path', function () use ($codes, $filter) {
        LineItem::factory()->for(Order::factory()->state(['code' => 'sku-1']))->for(Product::factory()->state(['sku' => 'SKU-1']))->create();
        LineItem::factory()->for(Order::factory()->state(['code' => 'sku-2']))->for(Product::factory()->state(['sku' => 'SKU-2']))->create();

        expect($codes('and', [$filter('lineItems.product.sku', 'equals', 'SKU-1')]))->toBe(['sku-1']);
    });

    it('ors a relation filter with its set', function () use ($codes, $filter) {
        $grace = Customer::factory()->create(['name' => 'Grace']);
        Order::factory()->for(Customer::factory()->state(['name' => 'Ada']))->create(['code' => 'ada-open', 'status' => 'open']);
        Order::factory()->for($grace)->createMany([['code' => 'grace-closed', 'status' => 'closed'], ['code' => 'grace-open', 'status' => 'open']]);

        expect($codes('or', [$filter('status', 'equals', 'closed'), $filter('customer.name', 'equals', 'Ada')]))
            ->toBe(['ada-open', 'grace-closed']);
    });

    it('keeps a relation filter inside the developer\'s constraints', function () use ($codes, $filter) {
        Order::factory()->for(Customer::factory()->state(['name' => 'Ada']))->create(['code' => 'team-1', 'team_id' => 1, 'status' => 'open']);
        Order::factory()->for(Customer::factory()->state(['name' => 'Grace']))->create(['code' => 'team-2', 'team_id' => 2, 'status' => 'closed']);

        expect($codes('or', [$filter('customer.name', 'equals', 'Ada'), $filter('status', 'equals', 'closed')], Order::query()->where('team_id', 1)))
            ->toBe(['team-1']);
    });

    it('qualifies a related column that a joined table shares', function () use ($codes, $filter) {
        $product = Product::factory()->create();
        LineItem::factory()->for(Order::factory()->state(['code' => 'with']))->for($product)->create();
        Order::factory()->create(['code' => 'without']);

        expect($codes('and', [$filter('products.id', 'equals', $product->id)]))->toBe(['with']);
    });
});

describe('doesnt have', function () use ($codes, $filter) {
    it('matches rows without related rows', function () use ($codes, $filter) {
        LineItem::factory()->for(Order::factory()->state(['code' => 'items']))->create();
        Order::factory()->create(['code' => 'empty']);

        expect($codes('and', [$filter('lineItems', 'doesntHave', null)]))->toBe(['empty']);
    });

    it('ors doesnt have with its set', function () use ($codes, $filter) {
        LineItem::factory()->for(Order::factory()->state(['code' => 'open-items', 'status' => 'open']))->create();
        LineItem::factory()->for(Order::factory()->state(['code' => 'closed-items', 'status' => 'closed']))->create();
        Order::factory()->create(['code' => 'open-empty', 'status' => 'open']);

        expect($codes('or', [$filter('status', 'equals', 'closed'), $filter('lineItems', 'doesntHave', null)]))
            ->toBe(['closed-items', 'open-empty']);
    });
});
