<?php

declare(strict_types=1);

use Dashworthy\ApiFilters\Data\SortData;
use Dashworthy\ApiFilters\Enums\SortOperator;

mutates(SortData::class);

it('keeps its column, direction and relation path', function () {
    $sortData = new SortData('name', SortOperator::Asc, 'customer');

    expect($sortData->column)->toBe('name')
        ->and($sortData->operator)->toBe(SortOperator::Asc)
        ->and($sortData->relation)->toBe('customer')
        ->and((new SortData('orders.total', SortOperator::Desc))->relation)->toBeNull();
});
