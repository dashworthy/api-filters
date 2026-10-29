<?php

declare(strict_types=1);

use Dashworthy\ApiFilters\ApiFilters;
use Dashworthy\ApiFilters\Columns\ColumnResolver;
use Illuminate\Validation\ValidationException;
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
