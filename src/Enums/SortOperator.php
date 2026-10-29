<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\Enums;

/**
 * The directions a client may name in a sort's sort_operator. A none sort is
 * skipped.
 */
enum SortOperator: string
{
    case Asc = 'asc';
    case Desc = 'desc';
    case None = 'none';
}
