<?php

declare(strict_types=1);

use Dashworthy\ApiFilters\Attributes\Filterable;

mutates(Filterable::class);

it('holds the columns and relation paths it lists', function () {
    $filterable = new Filterable(['status', 'total'], relations: ['customer.country']);

    expect($filterable->columns)->toBe(['status', 'total'])
        ->and($filterable->relations)->toBe(['customer.country']);
});

it('lists no relations unless it names them', function () {
    expect((new Filterable(['status']))->relations)->toBe([]);
});
