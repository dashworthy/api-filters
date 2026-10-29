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

    /**
     * @param  array{filter_set_operator: string, filters: list<array{column: string, filter_operator: string, value: scalar|list<scalar|null>|null}>}  $filterSet
     */
    public static function fromValidated(array $filterSet): self
    {
        return new self(
            operator: FilterSetOperator::from($filterSet['filter_set_operator']),
            filters: array_map(FilterData::fromValidated(...), $filterSet['filters']),
        );
    }
}
