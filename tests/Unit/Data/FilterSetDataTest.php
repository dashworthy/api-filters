<?php

declare(strict_types=1);

use Dashworthy\ApiFilters\Data\FilterData;
use Dashworthy\ApiFilters\Data\FilterSetData;
use Dashworthy\ApiFilters\Enums\FilterSetOperator;

mutates(FilterSetData::class);

it('keeps its operator and its filters in order', function () {
    $filters = [new FilterData('orders.status', 'equals', 'open'), new FilterData('orders.total', 'gt', 100)];
    $filterSet = new FilterSetData(FilterSetOperator::Or, $filters);

    expect($filterSet->operator)->toBe(FilterSetOperator::Or)
        ->and($filterSet->filters)->toBe($filters);
});
