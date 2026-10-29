<?php

declare(strict_types=1);

use Dashworthy\ApiFilters\Enums\FilterOperator;
use Dashworthy\ApiFilters\FilterOperations\Sets\NotIn;

mutates(NotIn::class);

beforeEach(fn () => createOrdersWithNotes());

it('excludes listed values and null rows', function () {
    expect(codesMatching(new NotIn, FilterOperator::NotIn, 'notes', ['rush']))->toBe(['gift']);
});

it('still matches the rest when the list holds null', function () {
    expect(codesMatching(new NotIn, FilterOperator::NotIn, 'notes', ['rush', null]))->toBe(['gift'])
        ->and(codesMatching(new NotIn, FilterOperator::NotIn, 'notes', [null]))->toBe(['gift', 'rush']);
});
