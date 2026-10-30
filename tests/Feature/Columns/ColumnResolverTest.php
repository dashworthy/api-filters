<?php

declare(strict_types=1);

use Dashworthy\ApiFilters\ApiFilters;
use Dashworthy\ApiFilters\Columns\ColumnResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Workbench\App\Models\Concerns\ExposesStatus;
use Workbench\App\Models\ListedOrder;
use Workbench\App\Models\Order;
use Workbench\App\Models\Profile;
use Workbench\App\Models\User;

mutates(ColumnResolver::class);

it('rejects a hidden column', function () {
    expect((new ColumnResolver)->resolve(new User, 'password'))->toBeNull();
});

it('rejects a hidden column in another case', function () {
    expect((new ColumnResolver)->resolve(new User, 'PASSWORD'))->toBeNull();
});

it('rejects a column outside visible', function () {
    expect((new ColumnResolver)->resolve(new Profile, 'email'))->toBeNull()
        ->and((new ColumnResolver)->resolve(new Profile, 'display_name'))->toBe('profiles.display_name');
});

it('uses one message for unknown and hidden columns', function () {
    $message = function (string $field): array {
        try {
            ApiFilters::for(User::query())->filter([
                ['filter_set_operator' => 'and', 'filters' => [['field' => $field, 'filter_operator' => 'equals', 'value' => 'x']]],
            ]);
        } catch (ValidationException $exception) {
            return $exception->errors();
        }

        return [];
    };

    expect($message('password'))->toBe(['filter_sets.0.filters.0.field' => ['This field cannot be filtered.']])
        ->and($message('nope'))->toBe($message('password'));
});

it('rejects a json path', function () {
    expect((new ColumnResolver)->resolve(new Order, 'notes->secret'))->toBeNull();
});

it('rejects a name with a trailing newline', function () {
    expect((new ColumnResolver)->resolve(new Order, "notes\n"))->toBeNull();
});

it('matches a visible column in any case', function () {
    expect((new ColumnResolver)->resolve(new Order, 'STATUS'))->toBe('orders.status');
});

describe('models that list their columns with #[Filterable]', function () {
    it('resolves only the listed columns', function () {
        expect((new ColumnResolver)->resolve(new ListedOrder, 'status'))->toBe('orders.status')
            ->and((new ColumnResolver)->resolve(new ListedOrder, 'notes'))->toBeNull();
    });

    it('matches a listed column in any case', function () {
        expect((new ColumnResolver)->resolve(new ListedOrder, 'STATUS'))->toBe('orders.status');
    });

    it('keeps a listed column the model hides unexposed', function () {
        expect((new ColumnResolver)->resolve(new ListedOrder, 'internal_reference'))->toBeNull();
    });

    it('does not read the table', function () {
        DB::enableQueryLog();

        (new ColumnResolver)->resolve(new ListedOrder, 'status');

        expect(DB::getQueryLog())->toBe([]);
    });

    it('keeps each model to its own columns when two share a table', function () {
        $resolver = new ColumnResolver;

        expect($resolver->resolve(new Order, 'notes'))->toBe('orders.notes')
            ->and($resolver->resolve(new ListedOrder, 'notes'))->toBeNull();
    });

    it('reads the attribute from a parent class', function () {
        $child = new class extends ListedOrder {};

        expect((new ColumnResolver)->resolve($child, 'status'))->toBe('orders.status')
            ->and((new ColumnResolver)->resolve($child, 'notes'))->toBeNull();
    });

    it('reads the attribute from a trait', function () {
        $model = new class extends Order
        {
            use ExposesStatus;

            protected $table = 'orders';
        };

        expect((new ColumnResolver)->resolve($model, 'status'))->toBe('orders.status')
            ->and((new ColumnResolver)->resolve($model, 'notes'))->toBeNull();
    });
});
