<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\Normalizers;

use Closure;
use Dashworthy\ApiFilters\Contracts\NormalizerContract;

/**
 * Reads the strings true and on as true, and false and off as false.
 */
final class BooleanNormalizer implements NormalizerContract
{
    public function handle(mixed $value, Closure $next): mixed
    {
        return $next(match ($value) {
            'true', 'on' => true,
            'false', 'off' => false,
            default => $value,
        });
    }
}
