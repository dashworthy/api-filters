<?php

declare(strict_types=1);

use Dashworthy\ApiFilters\Attributes\Filterable;

mutates(Filterable::class);

it('holds the columns it lists', function () {
    expect((new Filterable(['status', 'total']))->columns)->toBe(['status', 'total']);
});
