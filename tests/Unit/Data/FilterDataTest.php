<?php

declare(strict_types=1);

use Dashworthy\ApiFilters\Data\FilterData;
use Dashworthy\ApiFilters\Enums\FilterOperator;

mutates(FilterData::class);

it('builds from validated input', function () {
    $filter = FilterData::fromValidated(['column' => 'orders.status', 'filter_operator' => 'equals', 'value' => 'open']);

    expect($filter->column)->toBe('orders.status')
        ->and($filter->operator)->toBe(FilterOperator::Equals)
        ->and($filter->value)->toBe('open')
        ->and($filter->values)->toBe([]);
});

it('keeps a list in values', function () {
    $filter = FilterData::fromValidated(['column' => 'orders.notes', 'filter_operator' => 'in', 'value' => ['rush', null]]);

    expect($filter->operator)->toBe(FilterOperator::In)
        ->and($filter->value)->toBeNull()
        ->and($filter->values)->toBe(['rush', null]);
});
