<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\FilterOperations\Relations;

use Dashworthy\ApiFilters\Contracts\DatabaseAdapterContract;
use Dashworthy\ApiFilters\Contracts\FilterOperationContract;
use Dashworthy\ApiFilters\Data\FilterData;
use Dashworthy\ApiFilters\Enums\FilterOperator;
use Dashworthy\ApiFilters\Enums\FilterSetOperator;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

/**
 * Matches rows with no related row through the filter's relation path. Its
 * whole field is the path, so it compares no column and ignores its value.
 */
final class DoesntHave implements FilterOperationContract
{
    public function canHandle(FilterData $filterData): bool
    {
        return $filterData->operator === FilterOperator::DoesntHave->value;
    }

    /**
     * Any value.
     */
    public function rules(): array
    {
        return [];
    }

    public function handle(Builder $query, FilterData $filterData, FilterSetOperator $filterSetOperator, DatabaseAdapterContract $adapter): Builder
    {
        $relation = $filterData->relation ?? throw new LogicException('A doesntHave filter needs a relation path.');

        return $query->has($relation, '<', 1, $filterSetOperator->value);
    }
}
