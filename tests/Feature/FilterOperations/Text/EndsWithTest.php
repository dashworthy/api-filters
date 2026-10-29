<?php

declare(strict_types=1);

use Dashworthy\ApiFilters\Enums\FilterOperator;
use Dashworthy\ApiFilters\FilterOperations\Text\EndsWith;

mutates(EndsWith::class);

it('matches a suffix in any letter case', function () {
    createOrdersWithNotes();

    expect(codesMatching(new EndsWith, FilterOperator::EndsWith, 'notes', 'FT'))->toBe(['gift'])
        ->and(codesMatching(new EndsWith, FilterOperator::EndsWith, 'notes', 'if'))->toBe([]);
});
