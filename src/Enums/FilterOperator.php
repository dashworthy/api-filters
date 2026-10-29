<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\Enums;

/**
 * The operators a client may name in a filter's filter_operator.
 */
enum FilterOperator: string
{
    case Equals = 'equals';
    case DoesNotEquals = 'doesNotEquals';
    case Lt = 'lt';
    case Lte = 'lte';
    case Gt = 'gt';
    case Gte = 'gte';
    case In = 'in';
    case NotIn = 'notIn';
    case StartsWith = 'startsWith';
    case Contains = 'contains';
    case EndsWith = 'endsWith';
    case DoesNotContain = 'doesNotContain';
    case DateIs = 'dateIs';
    case DateIsNot = 'dateIsNot';
    case DateBefore = 'dateBefore';
    case DateAfter = 'dateAfter';
}
