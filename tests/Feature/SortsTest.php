<?php

declare(strict_types=1);

use Dashworthy\ApiFilters\ApiFilters;
use Dashworthy\ApiFilters\Http\FilterableRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;

mutates(FilterableRequest::class, ApiFilters::class);

/*
 | $errors sorts a query with an allow-list and returns the validation
 | errors, keyed by input path. $sort builds the wire format.
 */
$errors = function (mixed $sorts, array $allowedRelations = [], ?Model $model = null): array {
    try {
        ApiFilters::for(($model ?? new Order)->newQuery())->allowedRelations($allowedRelations)->sort($sorts);
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
};

$sort = fn (string $field, string $operator = 'asc'): array => ['field' => $field, 'sort_operator' => $operator];

it('sorts by the qualified column and the developer\'s path, spelled as the models list them', function () use ($sort) {
    expect(ApiFilters::for(Order::query())->allowedRelations(['customer'])->sort([$sort('TOTAL', 'desc'), $sort('customer.NAME')])->query()->toRawSql())
        ->toContain('order by `orders`.`total` desc, `customers`.`name` asc');
});

it('accepts a nested to one path', function () use ($errors, $sort) {
    expect($errors([$sort('customer.country.name')], ['customer.country']))->toBe([]);
});

describe('only visible columns can be sorted', function () use ($errors, $sort) {
    it('rejects a hidden column', function () use ($errors, $sort) {
        expect(array_keys($errors([$sort('password')], [], new User)))->toBe(['sorts.0.field']);
    });

    it('uses one message for unknown and hidden columns', function () use ($errors, $sort) {
        expect($errors([$sort('password')], [], new User))->toBe(['sorts.0.field' => ['This field cannot be sorted.']])
            ->and($errors([$sort('nope')], [], new User))->toBe($errors([$sort('password')], [], new User));
    });

    it('rejects a relation that is not allow-listed', function () use ($errors, $sort) {
        expect(array_keys($errors([$sort('customer.name')])))->toBe(['sorts.0.field']);
    });

    it('rejects a to many path', function () use ($errors, $sort) {
        expect($errors([$sort('lineItems.quantity')], ['lineItems']))->toBe(['sorts.0.field' => ['This field cannot be sorted.']]);
    });

    it('rejects a hidden related column', function () use ($errors, $sort) {
        expect(array_keys($errors([$sort('customer.password')], ['customer'])))->toBe(['sorts.0.field']);
    });
});

describe('sorts are limited and validated', function () use ($errors, $sort) {
    it('rejects too many sorts', function () use ($errors, $sort) {
        expect($errors(array_fill(0, 6, $sort('total'))))->toBe(['sorts' => ['No more than 5 sorts may be sent.']])
            ->and($errors(array_fill(0, 5, $sort('total'))))->toBe([]);
    });

    it('rejects an unknown sort operator', function () use ($errors, $sort) {
        expect(array_keys($errors([$sort('total', 'up')])))->toBe(['sorts.0.sort_operator']);
    });

    it('requires a field', function () use ($errors) {
        expect(array_keys($errors([['sort_operator' => 'asc']])))->toBe(['sorts.0.field']);
    });

    it('requires sorts to be a list', function () use ($errors) {
        expect(array_keys($errors('total')))->toBe(['sorts']);
    });

    it('reads the limit from configuration', function () use ($errors, $sort) {
        config()->set('api-filters.limits.sorts', 1);

        expect(array_keys($errors([$sort('total'), $sort('code')])))->toBe(['sorts']);
    });
});
