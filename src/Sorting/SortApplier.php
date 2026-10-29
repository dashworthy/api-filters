<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\Sorting;

use Dashworthy\ApiFilters\Data\SortData;
use Dashworthy\ApiFilters\Enums\SortOperator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Orders a query by one sort. A column of the query's model is ordered on
 * directly. A column reached through an allow-listed to-one path is ordered on
 * through power joins' left joins, so a row with no related row is kept and
 * sorts as NULL, and because every segment reaches at most one row the joins
 * never repeat a row. A none sort is skipped.
 */
final class SortApplier
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    public function apply(Builder $query, SortData $sortData): void
    {
        if ($sortData->operator === SortOperator::None) {
            return;
        }

        if ($sortData->relation === null) {
            $query->orderBy($sortData->column, $sortData->operator->value);

            return;
        }

        $query->orderByLeftPowerJoins("{$sortData->relation}.{$sortData->column}", $sortData->operator->value);
    }
}
