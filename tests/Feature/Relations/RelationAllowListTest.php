<?php

declare(strict_types=1);

use Dashworthy\ApiFilters\ApiFilters;
use Dashworthy\ApiFilters\Attributes\Filterable;
use Dashworthy\ApiFilters\Relations\RelationAllowList;
use Workbench\App\Models\Comment;
use Workbench\App\Models\LineItem;
use Workbench\App\Models\Order;
use Workbench\App\Models\Product;

mutates(RelationAllowList::class);

/*
 | $codes filters an orders query by line item quantity through a client's
 | path, with the paths Order lists, and returns the matching orders' codes.
 */
$codes = fn (string $field): array => ApiFilters::for(Order::query())
    ->apply(filterableRequest(['filter_sets' => [['filter_set_operator' => 'and', 'filters' => [['field' => $field, 'filter_operator' => 'gt', 'value' => 2]]]]]))
    ->query()
    ->orderBy('code')
    ->pluck('code')
    ->all();

describe('declaration', function () {
    it('refuses a forwarded builder method', function () {
        new RelationAllowList(new Order, ['truncate']);
    })->throws(LogicException::class, 'api-filters cannot allow the relation path [truncate]: [truncate] is not a public method of Workbench\App\Models\Order that declares an Eloquent relation as its return type.');

    it('leaves the table intact when a model lists a forwarded method', function () {
        Order::factory()->count(2)->create();
        $model = new #[Filterable(['status'], relations: ['truncate'])] class extends Order
        {
            protected $table = 'orders';
        };

        try {
            ApiFilters::for($model->newQuery());
        } catch (LogicException) {
        }

        expect(Order::query()->count())->toBe(2);
    });

    it('refuses a base model method a model lists', function () {
        Order::factory()->count(2)->create();
        $model = new #[Filterable(['status'], relations: ['save'])] class extends Order
        {
            protected $table = 'orders';
        };

        expect(fn () => ApiFilters::for($model->newQuery()))
            ->toThrow(LogicException::class, 'api-filters cannot allow the relation path [save]: [save] is not a public method')
            ->and(Order::query()->count())->toBe(2);
    });

    it('refuses a method without a relation return type', function () {
        new RelationAllowList(new Order, ['legacyCustomer']);
    })->throws(LogicException::class, 'api-filters cannot allow the relation path [legacyCustomer]: [legacyCustomer] is not a public method');

    it('refuses an unknown nested segment', function () {
        new RelationAllowList(new Order, ['customer.nope']);
    })->throws(LogicException::class, 'api-filters cannot allow the relation path [customer.nope]: [nope] is not a public method of Workbench\App\Models\Customer');
});

describe('matching', function () use ($codes) {
    beforeEach(function () {
        LineItem::factory()->for(Order::factory()->state(['code' => 'five']))->create(['quantity' => 5]);
        LineItem::factory()->for(Order::factory()->state(['code' => 'one']))->create(['quantity' => 1]);
    });

    it('allows the prefixes of a listed path', function () use ($codes) {
        expect($codes('lineItems.quantity'))->toBe(['five']);
    });

    it('accepts snake case segments', function () use ($codes) {
        expect($codes('line_items.quantity'))->toBe(['five']);
    });

    it('hands back the developer\'s spelling and the model the path reaches', function () {
        $relation = (new RelationAllowList(new Order, ['lineItems.product']))->find('line_items.product');

        expect($relation['path'] ?? null)->toBe('lineItems.product')
            ->and($relation['model'] ?? null)->toBeInstanceOf(Product::class);
    });

    it('marks whether every segment of a path reaches at most one row', function () {
        $relations = new RelationAllowList(new Order, ['customer.country', 'lineItems.product']);

        expect($relations->find('customer')['toOne'] ?? null)->toBeTrue()
            ->and($relations->find('customer.country')['toOne'] ?? null)->toBeTrue()
            ->and($relations->find('lineItems')['toOne'] ?? null)->toBeFalse()
            ->and($relations->find('lineItems.product')['toOne'] ?? null)->toBeFalse();
    });

    it('reaches no single model through a morph-to', function () {
        expect((new RelationAllowList(new Comment, ['commentable']))->find('commentable'))
            ->toBe(['path' => 'commentable', 'model' => null, 'toOne' => false]);
    });

    it('finds nothing for a path that is not listed', function () {
        expect((new RelationAllowList(new Order, ['lineItems']))->find('customer'))->toBeNull();
    });
});
