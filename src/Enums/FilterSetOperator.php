<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\Enums;

/**
 * How the filters inside one filter set are joined. Sets themselves are always
 * joined with AND.
 */
enum FilterSetOperator: string
{
    case And = 'and';
    case Or = 'or';
}
