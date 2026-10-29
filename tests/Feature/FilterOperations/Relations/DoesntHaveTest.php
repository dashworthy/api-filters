<?php

declare(strict_types=1);

use Dashworthy\ApiFilters\Adapters\MariaDbAdapter;
use Dashworthy\ApiFilters\Data\FilterData;
use Dashworthy\ApiFilters\Enums\FilterSetOperator;
use Dashworthy\ApiFilters\FilterOperations\Relations\DoesntHave;
use Illuminate\Database\Eloquent\Builder;
use Workbench\App\Models\LineItem;
use Workbench\App\Models\Order;

mutates(DoesntHave::class);

it('matches rows without related rows', function () {
    LineItem::factory()->for(Order::factory()->state(['code' => 'items']))->create();
    Order::factory()->create(['code' => 'empty']);

    $query = Order::query()->where(fn (Builder $group) => (new DoesntHave)->handle(
        $group,
        new FilterData('', 'doesntHave', null, 'lineItems'),
        FilterSetOperator::And,
        new MariaDbAdapter,
    ));

    expect($query->pluck('code')->all())->toBe(['empty']);
});

it('joins with its set operator', function () {
    LineItem::factory()->for(Order::factory()->state(['code' => 'closed', 'status' => 'closed']))->create();
    LineItem::factory()->for(Order::factory()->state(['code' => 'open', 'status' => 'open']))->create();
    Order::factory()->create(['code' => 'empty', 'status' => 'open']);

    $query = Order::query()->where(function (Builder $group) {
        $group->where('status', 'closed');
        (new DoesntHave)->handle($group, new FilterData('', 'doesntHave', null, 'lineItems'), FilterSetOperator::Or, new MariaDbAdapter);
    });

    expect($query->orderBy('code')->pluck('code')->all())->toBe(['closed', 'empty']);
});

it('needs a relation path', function () {
    (new DoesntHave)->handle(Order::query(), new FilterData('', 'doesntHave'), FilterSetOperator::And, new MariaDbAdapter);
})->throws(LogicException::class, 'A doesntHave filter needs a relation path.');
