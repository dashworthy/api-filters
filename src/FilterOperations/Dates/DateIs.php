<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\FilterOperations\Dates;

use Dashworthy\ApiFilters\Contracts\FilterOperationContract;
use Dashworthy\ApiFilters\Data\FilterData;
use Illuminate\Database\Eloquent\Builder;

/**
 * Matches rows whose column falls on the date, at any time of day.
 */
final class DateIs implements FilterOperationContract
{
    public function canHandle(FilterData $filterData): bool
    {
        return $filterData->operator === 'dateIs';
    }

    /**
     * A YYYY-MM-DD calendar date.
     */
    public function rules(): array
    {
        return ['required', 'date_format:Y-m-d'];
    }

    public function handle(Builder $query, FilterData $filterData): Builder
    {
        return $query->whereDate($filterData->column, '=', $filterData->stringValue());
    }
}
