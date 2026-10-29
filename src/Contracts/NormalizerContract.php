<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\Contracts;

use Closure;

/**
 * One step in the pipeline that turns a filter value as the client sent it
 * into the value the filter compares with. The normalizers listed in
 * api-filters.normalizers run in order, on each element of an in or notIn
 * list. Each passes its result to $next.
 */
interface NormalizerContract
{
    /**
     * @param  Closure(mixed): mixed  $next
     */
    public function handle(mixed $value, Closure $next): mixed;
}
