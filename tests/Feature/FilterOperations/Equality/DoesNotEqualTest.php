<?php

declare(strict_types=1);

use Dashworthy\ApiFilters\FilterOperations\Equality\DoesNotEqual;

mutates(DoesNotEqual::class);

beforeEach(fn () => createOrdersWithNotes());

it('excludes the value and null rows', function () {
    expect(codesMatching(new DoesNotEqual, 'doesNotEquals', 'notes', 'rush'))->toBe(['gift']);
});

it('matches non-null rows for null', function () {
    expect(codesMatching(new DoesNotEqual, 'doesNotEquals', 'notes', null))->toBe(['gift', 'rush']);
});
