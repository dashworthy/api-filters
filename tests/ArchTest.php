<?php

declare(strict_types=1);

use Dashworthy\ApiFilters\ApiFilters;
use Dashworthy\ApiFilters\ApiFiltersServiceProvider;
use Dashworthy\ApiFilters\Contracts\FilterOperationContract;
use Dashworthy\ApiFilters\Contracts\NormalizerContract;
use Illuminate\Foundation\Http\FormRequest;

arch()->preset()->php();

arch()->preset()->security();

arch('it will not use dd(), ddd(), env(), or exit()')
    ->expect(['dd', 'ddd', 'env', 'exit'])
    ->each->not->toBeUsed();

arch('the package source declares strict types')
    ->expect('Dashworthy\ApiFilters')
    ->toUseStrictTypes();

/*
 | dashworthy/pest-plugin-arch-rails: modules.
 |
 | The package namespace is a single module. Every class sits in a directory
 | that was approved before it was created, and each change that introduces a
 | directory adds it here only after that approval, so the layout is enforced
 | rather than remembered.
 |
 | The plugin reports a class that sits directly in the module, so the root
 | entry points are named in ignoring(): the service provider, which
 | composer.json registers, and the engine that applications import.
 */
arch('every class keeps to the approved directories')
    ->expect('Dashworthy\ApiFilters')
    ->toUseApprovedDirectories([
        'Attributes' => fn (string $class): bool => (new ReflectionClass($class))->getAttributes(Attribute::class) !== [],
        'Contracts' => fn (string $class): bool => interface_exists($class),
        'Data' => fn (string $class): bool => (new ReflectionClass($class))->isReadOnly(),
        'Enums' => UnitEnum::class,
        'FilterOperations' => FilterOperationContract::class,
        'Filtering',
        'Normalizers' => NormalizerContract::class,
        'Relations',
        'Sorting',
        'Http' => FormRequest::class,
    ], beneath: 'Dashworthy\ApiFilters')
    ->ignoring([ApiFiltersServiceProvider::class, ApiFilters::class]);

/*
 | ignoring() matches by prefix, so it also passes any class or directory whose
 | name starts with an entry point's (ApiFiltersServiceProviderContract, or
 | src/ApiFiltersServiceProvider/). This pins the root to the entry points
 | themselves; each change that adds one names its file here.
 */
it('keeps only the entry points at the package root', function () {
    $sourceRoot = dirname(__DIR__).'/src';

    expect(array_map(basename(...), glob($sourceRoot.'/*.php') ?: []))
        ->toEqualCanonicalizing(['ApiFilters.php', 'ApiFiltersServiceProvider.php'])
        ->and(glob($sourceRoot.'/ApiFilters*', GLOB_ONLYDIR) ?: [])
        ->toBe([]);
});

/*
 | A class is named for the directory it sits in. Each approved directory that
 | calls for a suffix adds it here.
 */
arch('every class is named for its directory')
    ->expect('Dashworthy\ApiFilters')
    ->toUseDirectorySuffixes([
        'Contracts' => 'Contract',
        'Data' => 'Data',
        'Filtering' => 'Applier',
        'Normalizers' => 'Normalizer',
        'Relations' => 'AllowList',
        'Sorting' => 'Applier',
        'Http' => 'Request',
    ], beneath: 'Dashworthy\ApiFilters');

/*
 | dashworthy/pest-plugin-arch-rails: coverage.
 |
 | Every class has a test at its mirrored path. Most classes need a database,
 | so src mirrors into tests/Feature; the plain Attributes, Data and
 | Normalizers layers mirror into tests/Unit, because the longest matching
 | source root wins.
 | Abstract classes, interfaces, traits and enums are skipped, so enums only
 | declare cases and any behaviour lives in a class that has a test.
 |
 | The paths are built from __DIR__ because the arch chain is assembled while
 | this file loads, before the application boots.
 */
$sourceRoot = dirname(__DIR__).'/src';
$testRoot = __DIR__;

arch('every class has a test at its mirrored path')
    ->expect('Dashworthy\ApiFilters')
    ->toHaveTests([
        $sourceRoot => $testRoot.'/Feature',
        $sourceRoot.'/Attributes' => $testRoot.'/Unit/Attributes',
        $sourceRoot.'/Data' => $testRoot.'/Unit/Data',
        $sourceRoot.'/Normalizers' => $testRoot.'/Unit/Normalizers',
    ]);
