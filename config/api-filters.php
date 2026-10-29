<?php

declare(strict_types=1);

use Dashworthy\ApiFilters\Adapters\MariaDbAdapter;
use Dashworthy\ApiFilters\Normalizers\BooleanNormalizer;
use Dashworthy\ApiFilters\Normalizers\NullNormalizer;

return [

    /*
    |--------------------------------------------------------------------------
    | Database Adapters
    |--------------------------------------------------------------------------
    |
    | Each connection driver maps to the adapter that writes its SQL, so a
    | filter gives the same results on every database. Filtering a query on a
    | driver with no adapter throws a LogicException. Each adapter implements
    | Dashworthy\ApiFilters\Contracts\DatabaseAdapterContract. An app that
    | reaches MariaDB through the mysql driver can map mysql to MariaDbAdapter.
    |
    */

    'adapters' => [
        'mariadb' => MariaDbAdapter::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Value Normalizers
    |--------------------------------------------------------------------------
    |
    | Every filter value runs through these classes in order before it is
    | checked against its operator, element by element for an in or notIn
    | list, each handing its result to the next. Each implements
    | Dashworthy\ApiFilters\Contracts\NormalizerContract. An empty list
    | compares every value exactly as the client sent it.
    |
    */

    'normalizers' => [
        BooleanNormalizer::class,
        NullNormalizer::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Input Limits
    |--------------------------------------------------------------------------
    |
    | Input over a limit is rejected with a validation error. filter_sets caps
    | the filter sets in one request, filters caps the filters across all of
    | its sets, and in_values caps the values listed in one in or notIn filter.
    |
    */

    'limits' => [
        'filter_sets' => 10,
        'filters' => 50,
        'in_values' => 100,
    ],

];
