<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\Normalizers;

use Closure;
use Dashworthy\ApiFilters\Contracts\NormalizerContract;

/**
 * Reads the string null as null.
 */
final class NullNormalizer implements NormalizerContract
{
    public function handle(mixed $value, Closure $next): mixed
    {
        return $next($value === 'null' ? null : $value);
    }
}
