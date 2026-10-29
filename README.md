<div align="center">
    <h1>API Filters</h1>
</div>

<p align="center">
    <a href="https://packagist.org/packages/dashworthy/api-filters"><img src="https://img.shields.io/packagist/v/dashworthy/api-filters.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/dashworthy/api-filters"><img src="https://img.shields.io/packagist/php-v/dashworthy/api-filters.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://packagist.org/packages/dashworthy/api-filters"><img src="https://badge.laravel.cloud/badge/dashworthy/api-filters?style=flat" alt="Laravel versions"></a>
    <a href="https://github.com/dashworthy/api-filters/actions"><img alt="GitHub Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/dashworthy/api-filters/tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/dashworthy/api-filters"><img src="https://img.shields.io/packagist/dt/dashworthy/api-filters.svg?style=flat-square" alt="Total Downloads"></a>
</p>

Apply API request filters and sorts to Eloquent queries without trusting client-supplied identifiers.

## Installation

You can install the package via Composer:

```bash
composer require dashworthy/api-filters
```

You may publish all of the package's resources at once:

```bash
php artisan vendor:publish --tag="api-filters"
```

Or, you may publish each resource individually:

### Publishing the Configuration File

```bash
php artisan vendor:publish --tag="api-filters-config"
```

### Publishing the Translations

```bash
php artisan vendor:publish --tag="api-filters-lang"
```

## Usage

<!-- Add a basic usage example here. -->

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Thank you for considering contributing to API Filters! Please review our [contributing guide](.github/CONTRIBUTING.md) to get started.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Andrew Leach](https://github.com/dashworthy)
- [All Contributors](../../contributors)

## License

API Filters is open-sourced software licensed under the [MIT license](LICENSE.md).
