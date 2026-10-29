<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\Data;

use Dashworthy\ApiFilters\Enums\SortOperator;

/**
 * One validated sort: the column it orders by, its direction, and the
 * allow-listed to-one relation path that reaches the column, if any. A column
 * of the query's model is qualified; a related column is not, because the
 * applier qualifies it inside the relation's subquery.
 */
final readonly class SortData
{
    public function __construct(
        public string $column,
        public SortOperator $operator,
        public ?string $relation = null,
    ) {}
}
