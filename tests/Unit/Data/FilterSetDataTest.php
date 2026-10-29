<?php

declare(strict_types=1);

use Dashworthy\ApiFilters\Data\FilterData;
use Dashworthy\ApiFilters\Data\FilterSetData;
use Dashworthy\ApiFilters\Enums\FilterOperator;
use Dashworthy\ApiFilters\Enums\FilterSetOperator;

mutates(FilterSetData::class);

it('builds its filters from validated input', function () {
    $filterSet = FilterSetData::fromValidated([
        'filter_set_operator' => 'or',
        'filters' => [
            ['column' => 'orders.status', 'filter_operator' => 'equals', 'value' => 'open'],
            ['column' => 'orders.total', 'filter_operator' => 'gt', 'value' => 100],
        ],
    ]);

    expect($filterSet->operator)->toBe(FilterSetOperator::Or)
        ->and($filterSet->filters)->toEqual([
            new FilterData('orders.status', FilterOperator::Equals, 'open'),
            new FilterData('orders.total', FilterOperator::Gt, 100),
        ]);
});
