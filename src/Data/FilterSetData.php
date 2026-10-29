<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\Data;

use Dashworthy\ApiFilters\Enums\FilterSetOperator;

/**
 * One validated filter set: its filters and the operator that joins them.
 */
final readonly class FilterSetData
{
    /**
     * @param  list<FilterData>  $filters
     */
    public function __construct(
        public FilterSetOperator $operator,
        public array $filters,
    ) {}
}
