<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\FilterOperations\Sets;

use Closure;
use Dashworthy\ApiFilters\Contracts\FilterOperationContract;
use Dashworthy\ApiFilters\Data\FilterData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Lang;

/**
 * Matches rows whose column equals any listed value. A null in the list also
 * matches NULL rows, inside a nested group so its OR stays within the filter.
 */
final class In implements FilterOperationContract
{
    public function canHandle(FilterData $filterData): bool
    {
        return $filterData->operator === 'in';
    }

    /**
     * A list of up to api-filters.limits.in_values values, each one value or
     * null.
     */
    public function rules(): array
    {
        return [
            'bail',
            'required',
            'list',
            'max:'.Config::integer('api-filters.limits.in_values'),
            function (string $attribute, mixed $values, Closure $fail): void {
                if (is_array($values) && array_filter($values, fn (mixed $value): bool => $value !== null && ! is_scalar($value)) !== []) {
                    $fail(Lang::get('api-filters::validation.invalid_value'));
                }
            },
        ];
    }

    public function handle(Builder $query, FilterData $filterData): Builder
    {
        $values = $filterData->listValue();
        $listed = array_values(array_filter($values, fn (mixed $value): bool => $value !== null));

        if (count($listed) === count($values)) {
            return $query->whereIn($filterData->column, $listed);
        }

        return $query->where(fn (Builder $nested) => $nested->whereIn($filterData->column, $listed)->orWhereNull($filterData->column));
    }
}
