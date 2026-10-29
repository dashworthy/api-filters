![api-filters — Turn API request filters into safe Eloquent queries.](art/banner.svg)

<p align="center">
    <a href="https://packagist.org/packages/dashworthy/api-filters"><img src="https://img.shields.io/packagist/v/dashworthy/api-filters.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/dashworthy/api-filters"><img src="https://img.shields.io/packagist/php-v/dashworthy/api-filters.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://packagist.org/packages/dashworthy/api-filters"><img src="https://badge.laravel.cloud/badge/dashworthy/api-filters?style=flat" alt="Laravel versions"></a>
    <a href="https://github.com/dashworthy/api-filters/actions"><img alt="GitHub Workflow Status (0.x)" src="https://img.shields.io/github/actions/workflow/status/dashworthy/api-filters/tests.yml?branch=0.x&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/dashworthy/api-filters"><img src="https://img.shields.io/packagist/dt/dashworthy/api-filters.svg?style=flat-square" alt="Total Downloads"></a>
</p>

Apply API request filters and sorts to Eloquent queries without trusting client-supplied identifiers.

> [!IMPORTANT]
> 0.x is in early development. The current release only scaffolds the package: it installs and publishes its config and translations. Filtering, relation filters and sorting arrive in the next 0.x releases. The [Usage](#usage) section describes the API those releases are designed to ship, and it may change before 1.0.

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

> [!NOTE]
> Planned. Nothing in this section is available yet.

### Request format

A client sends filter sets, sorts and an optional result window. It is the same format [dashworthy/visualizations](https://github.com/dashworthy/visualizations) uses:

```json
{
    "filter_sets": [
        {
            "filter_set_operator": "and",
            "filters": [
                { "field": "status", "value": "open", "filter_operator": "equals" },
                { "field": "customer.name", "value": "acme", "filter_operator": "contains" }
            ]
        }
    ],
    "sorts": [{ "field": "total", "sort_operator": "desc" }],
    "first": 0,
    "last": 50
}
```

### Filtering and sorting a request

Extend `FilterableRequest`. It validates `filter_sets`, `sorts`, `first` and `last`, and declares which relations a client may filter and sort through:

```php
use Dashworthy\ApiFilters\Http\FilterableRequest;

class ListOrdersRequest extends FilterableRequest
{
    public function allowedRelations(): array
    {
        return ['customer', 'lineItems.product'];
    }
}
```

Then apply the whole request to a query in one call. The package applies the filters, then the sorts, then the `first`/`last` window:

```php
public function index(ListOrdersRequest $request)
{
    return Order::query()
        ->where('team_id', $request->user()->team_id)
        ->apiFiltersAndSorts($request)
        ->get();
}
```

Filters never widen the constraints you apply first. Each filter set runs inside its own bracketed group, so an `or` set cannot return another team's rows.

### Filtering without a form request

```php
Order::query()
    ->apiFilters($filterSets, allowedRelations: ['customer'])
    ->apiSorts($sorts, allowedRelations: ['customer'])
    ->get();
```

`ApiFilters::for($query)` offers the same steps as a fluent object: `filter()`, `sort()`, `allowedRelations()` and `window($first, $last)`.

### Operators

| Operator | Matches | Value |
|---|---|---|
| `equals`, `doesNotEquals` | `=`, `!=` (`IS NULL`, `IS NOT NULL` for `null`) | a single value or `null` |
| `lt`, `lte`, `gt`, `gte` | `<`, `<=`, `>`, `>=` | a single value |
| `in`, `notIn` | `IN (…)`, `NOT IN (…)` | a non-empty list |
| `startsWith`, `contains`, `endsWith`, `doesNotContain` | `LIKE`, with `%` and `_` matched literally | a string or number |
| `dateIs`, `dateIsNot`, `dateBefore`, `dateAfter` | the date part of the column | `YYYY-MM-DD` |
| `doesntHave` | rows with no related row | ignored |

### What a client can and cannot reach

- **Columns.** A field must be a real column of the model's table, and not a hidden one (`$hidden`, or missing from a non-empty `$visible`). Fields such as `password` and `remember_token` cannot be filtered or sorted.
- **Relations.** A dotted field such as `customer.name` works only when `allowedRelations()` lists `customer`. Clients never choose which method is called on your model.
- **Sorting through relations.** Only to-one relations (`BelongsTo`, `HasOne`, `MorphOne`, `HasOneThrough`) can be sorted on.
- **Mistakes.** An unknown operator, a bad value or a field that cannot be used returns a 422 validation error keyed by its input path, for example `filter_sets.0.filters.1.field`.

### Limits

Every limit can be changed in `config/api-filters.php`:

| Limit | Default |
|---|---|
| Filter sets per request | 10 |
| Filters per request | 50 |
| Values in an `in` or `notIn` list | 100 |
| Sorts per request | 5 |
| Rows in one `first`/`last` window | 1000 |

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Thank you for considering contributing to API Filters! Please review our [contributing guide](https://github.com/dashworthy/api-filters/blob/HEAD/.github/CONTRIBUTING.md) to get started.

## Security Vulnerabilities

Please review [our security policy](https://github.com/dashworthy/api-filters/security/policy) on how to report security vulnerabilities.

## Credits

- [Andrew Leach](https://github.com/dashworthy)
- [All Contributors](../../contributors)

## License

API Filters is open-sourced software licensed under the [MIT license](LICENSE.md).
