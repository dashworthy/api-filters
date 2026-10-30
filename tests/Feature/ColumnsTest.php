<?php

declare(strict_types=1);

use Dashworthy\ApiFilters\ApiFilters;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Workbench\App\Models\Concerns\ExposesStatus;
use Workbench\App\Models\ListedOrder;
use Workbench\App\Models\Order;
use Workbench\App\Models\Profile;
use Workbench\App\Models\User;

mutates(ApiFilters::class);

/*
 | $resolve sorts a query on the model by the field and returns the column it
 | orders by, or null when the field is refused.
 */
$resolve = function (Model $model, string $field): ?string {
    try {
        $query = ApiFilters::for($model->newQuery())->apply(filterableRequest(['sorts' => [['field' => $field, 'sort_operator' => 'asc']]]))->query();
    } catch (ValidationException) {
        return null;
    }

    return $query->getQuery()->orders[0]['column'] ?? null;
};

it('rejects a hidden column', function () use ($resolve) {
    expect($resolve(new User, 'password'))->toBeNull();
});

it('rejects a hidden column in another case', function () use ($resolve) {
    expect($resolve(new User, 'PASSWORD'))->toBeNull();
});

it('rejects a column outside visible', function () use ($resolve) {
    expect($resolve(new Profile, 'email'))->toBeNull()
        ->and($resolve(new Profile, 'display_name'))->toBe('profiles.display_name');
});

it('uses one message for unknown and hidden columns', function () {
    $message = function (string $field): array {
        try {
            ApiFilters::for(User::query())->apply(filterableRequest(['filter_sets' => [
                ['filter_set_operator' => 'and', 'filters' => [['field' => $field, 'filter_operator' => 'equals', 'value' => 'x']]],
            ]]));
        } catch (ValidationException $exception) {
            return $exception->errors();
        }

        return [];
    };

    expect($message('password'))->toBe(['filter_sets.0.filters.0.field' => ['This field cannot be filtered.']])
        ->and($message('nope'))->toBe($message('password'));
});

it('rejects a json path', function () use ($resolve) {
    expect($resolve(new Order, 'notes->secret'))->toBeNull();
});

it('rejects a name with a trailing newline', function () use ($resolve) {
    expect($resolve(new Order, "notes\n"))->toBeNull();
});

it('matches a visible column in any case', function () use ($resolve) {
    expect($resolve(new Order, 'STATUS'))->toBe('orders.status');
});

it('exposes nothing on a model without #[Filterable]', function () use ($resolve) {
    $model = new class extends Model
    {
        protected $table = 'orders';
    };

    expect($resolve($model, 'status'))->toBeNull();
});

it('never reads the table', function () use ($resolve) {
    $unlisted = new class extends Model
    {
        protected $table = 'orders';
    };

    DB::enableQueryLog();

    $resolve(new Order, 'status');
    $resolve($unlisted, 'status');

    expect(DB::getQueryLog())->toBe([]);
});

describe('models that list their columns with #[Filterable]', function () use ($resolve) {
    it('resolves only the listed columns', function () use ($resolve) {
        expect($resolve(new ListedOrder, 'status'))->toBe('orders.status')
            ->and($resolve(new ListedOrder, 'notes'))->toBeNull();
    });

    it('matches a listed column in any case', function () use ($resolve) {
        expect($resolve(new ListedOrder, 'STATUS'))->toBe('orders.status');
    });

    it('keeps a listed column the model hides unexposed', function () use ($resolve) {
        expect($resolve(new ListedOrder, 'internal_reference'))->toBeNull();
    });

    it('keeps each model to its own columns when two share a table', function () use ($resolve) {
        expect($resolve(new Order, 'notes'))->toBe('orders.notes')
            ->and($resolve(new ListedOrder, 'notes'))->toBeNull();
    });

    it('reads the attribute from a parent class', function () use ($resolve) {
        $child = new class extends ListedOrder {};

        expect($resolve($child, 'status'))->toBe('orders.status')
            ->and($resolve($child, 'notes'))->toBeNull();
    });

    it('reads the attribute from a trait', function () use ($resolve) {
        $model = new class extends Order
        {
            use ExposesStatus;

            protected $table = 'orders';
        };

        expect($resolve($model, 'status'))->toBe('orders.status')
            ->and($resolve($model, 'notes'))->toBeNull();
    });
});
