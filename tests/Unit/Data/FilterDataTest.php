<?php

declare(strict_types=1);

use Dashworthy\ApiFilters\Data\FilterData;

mutates(FilterData::class);

it('reads a single value', function (mixed $value) {
    expect((new FilterData('orders.status', 'equals', $value))->scalarValue())->toBe($value);
})->with([
    'a string' => 'open',
    'a number' => 1.5,
    'a boolean' => false,
    'null' => [null],
]);

it('refuses a list as a single value', function () {
    (new FilterData('orders.status', 'equals', ['open']))->scalarValue();
})->throws(LogicException::class, "The [equals] filter's value is not a single value.");

it('reads a string', function () {
    expect((new FilterData('orders.placed_at', 'dateIs', '2026-01-15'))->stringValue())->toBe('2026-01-15');
});

it('refuses a number as a string', function () {
    (new FilterData('orders.placed_at', 'dateIs', 20260115))->stringValue();
})->throws(LogicException::class, "The [dateIs] filter's value is not a string.");

it('reads a list of single values and nulls', function () {
    expect((new FilterData('orders.notes', 'in', ['rush', 1, null]))->listValue())->toBe(['rush', 1, null]);
});

it('refuses a value that is not a list', function (mixed $value) {
    (new FilterData('orders.notes', 'in', $value))->listValue();
})->throws(LogicException::class, "The [in] filter's value is not a list.")->with([
    'a string' => 'rush',
    'a keyed array' => [['a' => 'rush']],
]);

it('refuses a nested list', function () {
    (new FilterData('orders.notes', 'in', [['rush']]))->listValue();
})->throws(LogicException::class, "The [in] filter's value is not a list of single values.");
