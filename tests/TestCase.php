<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\Tests;

use Dashworthy\ApiFilters\ApiFiltersServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            ApiFiltersServiceProvider::class,
        ];
    }
}
