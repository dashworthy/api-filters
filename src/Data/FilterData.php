<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\Data;

use Dashworthy\ApiFilters\Enums\FilterOperator;

/**
 * One validated filter: the qualified column it compares, its operator and its
 * normalized value. An in or notIn filter carries its list in values; every
 * other operator carries one value.
 */
final readonly class FilterData
{
    /**
     * @param  list<scalar|null>  $values
     */
    public function __construct(
        public string $column,
        public FilterOperator $operator,
        public string|int|float|bool|null $value = null,
        public array $values = [],
    ) {}

    /**
     * @param  array{column: string, filter_operator: string, value: scalar|list<scalar|null>|null}  $filter
     */
    public static function fromValidated(array $filter): self
    {
        $value = $filter['value'];

        return new self(
            column: $filter['column'],
            operator: FilterOperator::from($filter['filter_operator']),
            value: is_array($value) ? null : $value,
            values: is_array($value) ? $value : [],
        );
    }
}
