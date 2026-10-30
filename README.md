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
> 0.x is in early development. The API may change before 1.0.

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

### Request format

A client sends filter sets and sorts:

```json
{
    "filter_sets": [
        {
            "filter_set_operator": "and",
            "filters": [
                { "field": "status", "value": "open", "filter_operator": "equals" },
                { "field": "total", "value": 100, "filter_operator": "gt" }
            ]
        }
    ],
    "sorts": [{ "field": "total", "sort_operator": "desc" }]
}
```

The filters inside a set are joined by its `filter_set_operator`, `and` or `or`. Sets are always joined to each other with AND.

### Filtering and sorting a query

Take the request through a form request that extends `FilterableRequest`:

```php
use Dashworthy\ApiFilters\Http\FilterableRequest;

class ListOrdersRequest extends FilterableRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Order::class);
    }
}
```

Then apply it to any Eloquent query, after your own constraints:

```php
public function index(ListOrdersRequest $request)
{
    return Order::query()
        ->where('orders.team_id', $request->user()->team_id)
        ->apiFiltersAndSorts($request)
        ->get();
}
```

- `FilterableRequest` checks what the operations decide before your controller runs: the shape and limits of both keys, an operator that some operation handles, and a value that passes that operation's `rules()` once the normalizers have read it.
- The request does not know your model. `apiFiltersAndSorts()` checks each field against the query's model and returns a 422 for a field the model does not expose. Every field is checked before any is applied, so a rejected request leaves the query as it was.
- `apiFiltersAndSorts()` applies the filters, then the sorts.
- Filters never widen the constraints you apply first. Each filter set runs inside its own bracketed group, so an `or` set cannot return another team's rows.
- `FilterableRequest` has no `authorize()`, and Laravel lets every request through a form request without one, so add your own.

### Choosing what clients can use

A client can filter and sort only by the columns and relations a model lists with the `#[Filterable]` attribute:

```php
use Dashworthy\ApiFilters\Attributes\Filterable;

#[Filterable(
    columns: ['id', 'status', 'total', 'placed_at', 'created_at'],
    relations: ['customer', 'lineItems.product'],
)]
class Order extends Model
{
}
```

- A model without the attribute exposes nothing, so every field on it gets the usual 422.
- A field matches a listed column in any letter case. Your spelling from the list is what reaches SQL.
- The key and timestamps are not added for you. List `id` or `created_at` if clients filter or sort by them.
- `$hidden` and `$visible` still win, so a listed column the model hides stays out of reach.
- `relations` lists the [relation paths](#filtering-through-relations) clients can reach from this model. It lists none unless you name them.
- The attribute can sit on the model, on one of its traits or on a parent class, like Laravel's `#[Hidden]`. The nearest one is used whole, so a subclass's list replaces its parent's.
- The package never reads the table. List real columns only: a listed name that is not a column fails when the query runs.

### Operators

These operators ship with the package. You can [add your own](#adding-your-own-operators).

| Operator | Matches | Value |
|---|---|---|
| `equals`, `doesNotEquals` | `=`, `!=` (`IS NULL`, `IS NOT NULL` for `null`). `doesNotEquals` leaves out NULL rows | a single value or `null` |
| `lt`, `lte`, `gt`, `gte` | `<`, `<=`, `>`, `>=` | a single value |
| `in`, `notIn` | `IN (…)`, `NOT IN (…)`. A `null` in the list matches NULL rows for `in`; NULL rows never match `notIn` | a non-empty list |
| `startsWith`, `contains`, `endsWith`, `doesNotContain` | `LIKE` in any letter case. `doesNotContain` also matches NULL rows. A `%` or `_` in the value is a wildcard | a string or number |
| `dateIs`, `dateIsNot`, `dateBefore`, `dateAfter` | the date part of the column, at any time of day | `YYYY-MM-DD` |
| `doesntHave` | rows with no related row through the field, which is a [relation path](#filtering-through-relations) | ignored |

The strings `true` and `on`, `false` and `off`, and `null` are read as `true`, `false` and `null`, element by element in a list. Every other string, numeric ones included, is compared as sent, so `"007"` never matches `7`.

### Filtering through relations

A dotted field such as `customer.name` filters through a relation path the query's model lists in `relations`.

- **Paths.** Write a path as relation method names joined by dots, starting from the model that lists it, such as `lineItems.product` on `Order`. A path also allows each shorter path it starts with, so `lineItems.product` allows `lineItems` as well.
- **The queried model decides.** Only the `relations` of the query's model count. List the full path there: `customer.country` on `Order`, not `country` on `Customer`. `Customer`'s own `relations` apply when you query customers.
- **Spelling.** Clients may write a segment in snake_case: `line_items.quantity` matches `lineItems`.
- **Matching.** `customer.name equals Ada` matches the orders with at least one related row where the condition holds. Two filters on the same relation match on their own, so each can be met by a different related row.
- **Related columns** follow the related model's own `#[Filterable]` `columns`. A hidden column of `Customer` is rejected with the same message as an unknown one.
- **Morph-to.** A path through a morph-to reaches a different model from row to row, so no column can be filtered through it. `doesntHave` still works on one.
- **`doesntHave`** takes a relation path as its field and matches the rows with no related row: `{ "field": "lineItems", "filter_operator": "doesntHave", "value": null }`.
- **Declared relations only.** Every segment must be a public method that declares an Eloquent relation as its return type, such as `public function customer(): BelongsTo`. `apiFiltersAndSorts()` checks each listed path before it reads any input, and throws a `LogicException` otherwise. A relation method without a return type needs one first.

Eloquent calls the method a relation path names to build the relation. Without a list, a client that sends `truncate.id` makes Eloquent call `Order::truncate()`, which empties the table. Here a client's path is only ever looked up in your list. What reaches Eloquent is your spelling of the path, and each listed method was checked before it was ever called.

### Sorting

The request's `sorts` apply after its filters.

- **Format.** Each sort is `{ "field": "total", "sort_operator": "desc" }`, with `asc`, `desc` or `none`. Sorts apply in order, so a later one only breaks ties, and `none` is skipped.
- **Fields** follow the filter rules: a [column the model lists](#choosing-what-clients-can-use), or a dotted field through a path it lists. Every rejected field gets the same message.
- **To-one paths only.** Each segment must be a belongs-to, has-one, morph-one or has-one-through relation, so `customer.country.name` works and `lineItems.quantity` is rejected.
- **Joins.** A relation sort uses [power joins](https://github.com/kirschbaum-development/eloquent-power-joins)' `orderByLeftPowerJoins()`. It is a left join, so a row without a related row stays and sorts as NULL. The join adds the related table to the query, so qualify your own `where` columns, such as `orders.team_id`, when the related table has a column of the same name. Each join follows a foreign key, so index the keys on the paths you list, such as `orders.customer_id`.

### Adding your own operators

An operator is a class that implements `Dashworthy\ApiFilters\Contracts\FilterOperationContract`:

| Method | Job |
|---|---|
| `canHandle(FilterData $filterData)` | Says whether it applies this filter, usually by its `operator` |
| `rules()` | Returns the Laravel validation rules the value must pass |
| `handle(Builder $query, FilterData $filterData)` | Adds the filter's condition to `$query` |

List the class under `operations` in `config/api-filters.php`. Every class whose `canHandle()` accepts a filter checks the value against its `rules()`, then adds its condition, so two classes that handle the same operator both apply. An operator that no class handles gets a 422. To replace a built-in operator, take its class out of the list and add yours.

`canHandle()` is asked twice: when the request is validated, with the field as the client sent it, and when the filter is applied, with the resolved column. Decide by the `operator`, so both give the same answer.

Operations write their SQL with Laravel's query builder, which writes it for each database: `whereLike()` matches in any letter case and `whereDate()` compares calendar dates wherever they run. Where an operator needs different SQL on your database, replace its class.

This operation keeps the rows whose point column lies inside a polygon:

```php
use Closure;
use Dashworthy\ApiFilters\Contracts\FilterOperationContract;
use Dashworthy\ApiFilters\Data\FilterData;
use Illuminate\Database\Eloquent\Builder;

class WithinPolygon implements FilterOperationContract
{
    public function canHandle(FilterData $filterData): bool
    {
        return $filterData->operator === 'withinPolygon';
    }

    /**
     * A closed ring of up to 1000 [longitude, latitude] positions.
     */
    public function rules(): array
    {
        return [
            'bail',
            'required',
            'list',
            'min:4',
            'max:1000',
            function (string $attribute, array $ring, Closure $fail): void {
                $isPosition = fn (mixed $position): bool => is_array($position) && array_is_list($position)
                    && count($position) === 2 && is_numeric($position[0]) && is_numeric($position[1]);

                if (array_filter($ring, $isPosition) !== $ring || $ring[0] != $ring[array_key_last($ring)]) {
                    $fail('The :attribute must be a closed ring of [longitude, latitude] positions.');
                }
            },
        ];
    }

    public function handle(Builder $query, FilterData $filterData): Builder
    {
        $ring = array_map(fn (array $position): array => array_map(floatval(...), $position), $filterData->value);
        $polygon = json_encode(['type' => 'Polygon', 'coordinates' => [$ring]], JSON_THROW_ON_ERROR);
        $column = $query->getQuery()->getGrammar()->wrap($filterData->column);

        return $query->whereRaw("ST_Contains(ST_GeomFromGeoJSON(?), {$column})", [$polygon]);
    }
}
```

```php
'operations' => [
    // ...the built-in operations
    WithinPolygon::class,
],
```

A client can then send `{ "field": "location", "filter_operator": "withinPolygon", "value": [[0, 0], [2, 0], [2, 2], [0, 2], [0, 0]] }`.

The package does the risky parts before `handle()` runs:

- `$filterData->column` is a column the model exposes, already checked and qualified with its table.
- The value has passed `rules()`. `scalarValue()`, `stringValue()` and `listValue()` read it back with a type.
- The condition lands in a group of the filter's own, joined to its set by the set's `and` or `or`, inside the set's group. Even an `orWhere()` in it cannot widen the constraints you applied first.
- For a relation field such as `customer.location`, `handle()` runs inside the relation's `has()` query with the column qualified for the related table, so an operation needs no relation code.

The rest is up to you. Bind every value, as the `?` in `whereRaw()` does above, and wrap the column with the grammar. Never write client input into SQL. Cap the size of a value in `rules()`, as `max:1000` does here, so one request cannot make the database do unbounded work.

### What a client can and cannot reach

- **Columns.** A field must be a column the model's `#[Filterable]` attribute lists, matched in any letter case. A model without the attribute exposes none. A listed column the model hides (`$hidden`, or missing from a non-empty `$visible`) stays out of reach, so `password` cannot be filtered even when it is listed.
- **Your spelling only.** Columns and relation paths come from your `#[Filterable]` lists. A client's field is only looked up in them, so no identifier a client sends reaches SQL.
- **Relations.** A dotted field works only through a path the query's model lists. Clients never choose which method is called on your model.
- **Mistakes.** An unknown operator, a bad value or a field that cannot be used returns a 422 validation error keyed by its input path, for example `filter_sets.0.filters.1.field`. An unknown column and a hidden one get the same message. The form request reports operators and values before your controller runs, and `apiFiltersAndSorts()` reports fields once those pass.

### Limits

Every limit can be changed in `config/api-filters.php`:

| Limit | Key | Default |
|---|---|---|
| Filter sets per request | `limits.filter_sets` | 10 |
| Filters across all sets | `limits.filters` | 50 |
| Values in one `in` or `notIn` list | `limits.in_values` | 100 |
| Sorts per request | `limits.sorts` | 5 |

### Security model

| Threat | What the package does |
|---|---|
| A client's relation name calls a model method: `truncate.id` empties the table | A relation path is only looked up in the paths the model lists, and each listed segment was checked to be a relation method before it was ever called |
| A client probes a column, such as `password startsWith $2y$10$a` | Only the columns a model lists with `#[Filterable]` can be filtered or sorted. A hidden column never can, even when listed |
| A morph-to path reaches columns of a model that never listed them | No column can be filtered or sorted through a morph-to, because the model it reaches differs from row to row |
| An `or` set returns other teams' rows | Each set, and each filter inside it, runs in a where group of its own |
| Input too large to run cheaply | Limits on filter sets, filters, `in` values and sorts |
| An unknown operator is ignored, returning every row | An operator that no operation handles is a 422 |
| A value of the wrong type, such as `in` with a string | Every value is checked against its operation's rules before anything is applied |
| `%` and `_` in a pattern are LIKE wildcards | They are wildcards by design. They only reach exposed columns, and the limits cap how many patterns one request sends |

## Testing

The suite runs on MariaDB. Start one, then run every check:

```bash
docker run -d -p 3306:3306 -e MARIADB_ALLOW_EMPTY_ROOT_PASSWORD=1 -e MARIADB_DATABASE=api_filters mariadb:11.4
composer test
```

`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD` override the defaults in `phpunit.xml.dist`.

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
