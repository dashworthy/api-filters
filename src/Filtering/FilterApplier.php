<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\Filtering;

use Dashworthy\ApiFilters\Contracts\DatabaseAdapterContract;
use Dashworthy\ApiFilters\Contracts\FilterOperationContract;
use Dashworthy\ApiFilters\Data\FilterData;
use Dashworthy\ApiFilters\Enums\FilterOperator;
use Dashworthy\ApiFilters\Enums\FilterSetOperator;
use Dashworthy\ApiFilters\FilterOperations\Dates\DateAfter;
use Dashworthy\ApiFilters\FilterOperations\Dates\DateBefore;
use Dashworthy\ApiFilters\FilterOperations\Dates\DateIs;
use Dashworthy\ApiFilters\FilterOperations\Dates\DateIsNot;
use Dashworthy\ApiFilters\FilterOperations\Equality\DoesNotEqual;
use Dashworthy\ApiFilters\FilterOperations\Equality\Equals;
use Dashworthy\ApiFilters\FilterOperations\Equality\GreaterThan;
use Dashworthy\ApiFilters\FilterOperations\Equality\GreaterThanOrEqualTo;
use Dashworthy\ApiFilters\FilterOperations\Equality\LessThan;
use Dashworthy\ApiFilters\FilterOperations\Equality\LessThanOrEqualTo;
use Dashworthy\ApiFilters\FilterOperations\Sets\In;
use Dashworthy\ApiFilters\FilterOperations\Sets\NotIn;
use Dashworthy\ApiFilters\FilterOperations\Text\Contains;
use Dashworthy\ApiFilters\FilterOperations\Text\DoesNotContain;
use Dashworthy\ApiFilters\FilterOperations\Text\EndsWith;
use Dashworthy\ApiFilters\FilterOperations\Text\StartsWith;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Hands each filter to the operation for its operator, with the adapter for
 * the query's database.
 */
final class FilterApplier
{
    public function __construct(private readonly DatabaseAdapterContract $adapter) {}

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $group
     */
    public function apply(Builder $group, FilterData $filterData, FilterSetOperator $filterSetOperator): void
    {
        $this->operation($filterData->operator)->handle($group, $filterData, $filterSetOperator, $this->adapter);
    }

    /**
     * The match covers every operator, so an operator without an operation
     * fails static analysis instead of being skipped at runtime.
     */
    private function operation(FilterOperator $operator): FilterOperationContract
    {
        return match ($operator) {
            FilterOperator::Equals => new Equals,
            FilterOperator::DoesNotEquals => new DoesNotEqual,
            FilterOperator::Lt => new LessThan,
            FilterOperator::Lte => new LessThanOrEqualTo,
            FilterOperator::Gt => new GreaterThan,
            FilterOperator::Gte => new GreaterThanOrEqualTo,
            FilterOperator::In => new In,
            FilterOperator::NotIn => new NotIn,
            FilterOperator::StartsWith => new StartsWith,
            FilterOperator::Contains => new Contains,
            FilterOperator::EndsWith => new EndsWith,
            FilterOperator::DoesNotContain => new DoesNotContain,
            FilterOperator::DateIs => new DateIs,
            FilterOperator::DateIsNot => new DateIsNot,
            FilterOperator::DateBefore => new DateBefore,
            FilterOperator::DateAfter => new DateAfter,
        };
    }
}
