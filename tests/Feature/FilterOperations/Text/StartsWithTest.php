<?php

declare(strict_types=1);

use Dashworthy\ApiFilters\FilterOperations\Text\StartsWith;

mutates(StartsWith::class);

it('matches a prefix in any letter case', function () {
    createOrdersWithNotes();

    expect(codesMatching(new StartsWith, 'startsWith', 'notes', 'Ru'))->toBe(['rush'])
        ->and(codesMatching(new StartsWith, 'startsWith', 'notes', 'us'))->toBe([]);
});
