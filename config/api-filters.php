<?php

declare(strict_types=1);

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
use Dashworthy\ApiFilters\FilterOperations\Relations\DoesntHave;
use Dashworthy\ApiFilters\FilterOperations\Sets\In;
use Dashworthy\ApiFilters\FilterOperations\Sets\NotIn;
use Dashworthy\ApiFilters\FilterOperations\Text\Contains;
use Dashworthy\ApiFilters\FilterOperations\Text\DoesNotContain;
use Dashworthy\ApiFilters\FilterOperations\Text\EndsWith;
use Dashworthy\ApiFilters\FilterOperations\Text\StartsWith;
use Dashworthy\ApiFilters\Normalizers\BooleanNormalizer;
use Dashworthy\ApiFilters\Normalizers\NullNormalizer;

return [

    /*
    |--------------------------------------------------------------------------
    | Filter Operations
    |--------------------------------------------------------------------------
    |
    | The operators a client can name in filter_operator. Every class here
    | whose canHandle() accepts a filter checks the value against its rules()
    | and adds its condition, and a filter that none can handle is rejected.
    | Each implements Dashworthy\ApiFilters\Contracts\FilterOperationContract.
    | Add your own class to give clients a new operator, or swap a built-in
    | class for yours to replace that operator.
    |
    */

    'operations' => [
        Equals::class,
        DoesNotEqual::class,
        LessThan::class,
        LessThanOrEqualTo::class,
        GreaterThan::class,
        GreaterThanOrEqualTo::class,
        In::class,
        NotIn::class,
        StartsWith::class,
        Contains::class,
        EndsWith::class,
        DoesNotContain::class,
        DateIs::class,
        DateIsNot::class,
        DateBefore::class,
        DateAfter::class,
        DoesntHave::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Value Normalizers
    |--------------------------------------------------------------------------
    |
    | Every filter value runs through these classes in order before it is
    | checked against its operation's rules, element by element for an in
    | or notIn list, each handing its result to the next. Each implements
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
