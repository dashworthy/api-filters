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

A client sends filter sets, sorts and an optional result window:

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
    "sorts": [{ "field": "total", "sort_operator": "desc" }],
    "first": 0,
    "last": 50
}
```

The filters inside a set are joined by its `filter_set_operator`, `and` or `or`. Sets are always joined to each other with AND.

### Filtering a query

Apply a request's `filter_sets` to any Eloquent query:

```php
public function index(Request $request)
{
    return Order::query()
        ->where('team_id', $request->user()->team_id)
        ->apiFilters($request->input('filter_sets', []))
        ->get();
}
```

`ApiFilters::for($query)->filter($filterSets)->query()` does the same through a fluent object.

Filters never widen the constraints you apply first. Each filter set runs inside its own bracketed group, so an `or` set cannot return another team's rows. The whole input is validated before anything is applied, so a rejected request leaves the query as it was.

### Choosing the columns clients can use

By default a client can filter and sort by any column of the model's table that the model does not hide with `$hidden` or `$visible`. To expose only some columns, list them with the `#[Filterable]` attribute:

```php
use Dashworthy\ApiFilters\Attributes\Filterable;

#[Filterable(['id', 'status', 'total', 'placed_at', 'created_at'])]
class Order extends Model
{
}
```

- Only the listed columns can be filtered or sorted, matched in any letter case. Any other field gets the usual 422.
- The key and timestamps are not added for you. List `id` or `created_at` if clients filter or sort by them.
- `$hidden` and `$visible` still win, so a listed column the model hides stays out of reach.
- A related model's `#[Filterable]` decides which of its columns a [relation path](#filtering-through-relations) can reach.
- The attribute can sit on the model, on one of its traits or on a parent class, like Laravel's `#[Hidden]`.
- With the attribute, the package does not read the table to find the model's columns. List real columns only: a listed name that is not a column fails when the query runs.

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

The strings `true` and `on`, `false` and `off`, and `null` are read as `true`, `false` and `null`, element by element in a list. Every other string, numeric ones included, is compared as sent, so `"007"` never matches `7`. The `normalizers` key in `config/api-filters.php` lists the classes that do this. Each implements `Dashworthy\ApiFilters\Contracts\NormalizerContract` and hands its result to `$next`; an empty list switches them off.

### Filtering through relations

A dotted field such as `customer.name` filters through a relation, once the query allow-lists its path:

```php
Order::query()->apiFilters($filterSets, allowedRelations: ['customer', 'lineItems.product']);

ApiFilters::for($query)->allowedRelations(['customer', 'lineItems.product'])->filter($filterSets);
```

- **Paths.** Write a path as the model's relation method names joined by dots. A path also allows each shorter path it starts with, so `lineItems.product` allows `lineItems` as well.
- **Spelling.** Clients may write a segment in snake_case: `line_items.quantity` matches `lineItems`.
- **Matching.** `customer.name equals Ada` matches the orders with at least one related row where the condition holds. Two filters on the same relation match on their own, so each can be met by a different related row.
- **Related columns** follow the related model's rules: its `#[Filterable]` list, or else its visible columns. A hidden column of `Customer` is rejected with the same message as an unknown one.
- **`doesntHave`** takes a relation path as its field and matches the rows with no related row: `{ "field": "lineItems", "filter_operator": "doesntHave", "value": null }`.
- **Declared relations only.** Every segment must be a public method that declares an Eloquent relation as its return type, such as `public function customer(): BelongsTo`. `allowedRelations()` checks each one before any input is read, and throws a `LogicException` otherwise. A relation method without a return type needs one first.

Eloquent calls the method a relation path names to build the relation. Without an allow-list, a client that sends `truncate.id` makes Eloquent call `Order::truncate()`, which empties the table. Here a client's path is only ever looked up in your list. What reaches Eloquent is your spelling of the path, and each listed method was checked before it was ever called.

### Sorting

```php
Order::query()->apiSorts($request->input('sorts', []), allowedRelations: ['customer']);

ApiFilters::for($query)->allowedRelations(['customer'])->sort($sorts);
```

- **Format.** Each sort is `{ "field": "total", "sort_operator": "desc" }`, with `asc`, `desc` or `none`. Sorts apply in order, so a later one only breaks ties, and `none` is skipped.
- **Fields** follow the filter rules: a [column the model exposes](#choosing-the-columns-clients-can-use), or a dotted field through an allow-listed path. Every rejected field gets the same message.
- **To-one paths only.** Each segment must be a belongs-to, has-one, morph-one or has-one-through relation, so `customer.country.name` works and `lineItems.quantity` is rejected.
- **Joins.** A relation sort uses [power joins](https://github.com/kirschbaum-development/eloquent-power-joins)' `orderByLeftPowerJoins()`. It is a left join, so a row without a related row stays and sorts as NULL. The join adds the related table to the query, so qualify your own `where` columns, such as `orders.team_id`, when the related table has a column of the same name. Each join follows a foreign key, so index the keys on the paths you allow, such as `orders.customer_id`.

### Result window

`window($first, $last)` keeps the rows from position `first`, counted from zero, up to but not including `last`:

```php
ApiFilters::for($query)->sort($sorts)->window(0, 50);
```

`first` must be 0 or more, `last` must come after it, and the window holds at most 1000 rows. Anything else is a 422.

`ApiFilters::windowRules($first)` returns those rules, keyed `first` and `last`, for a request that validates the window itself. `FilterableRequest` already uses them.

### Form requests

Extend `FilterableRequest` for an endpoint that takes `filter_sets`, `sorts`, `first` and `last`:

```php
use Dashworthy\ApiFilters\Http\FilterableRequest;

class ListOrdersRequest extends FilterableRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Order::class);
    }

    public function allowedRelations(): array
    {
        return ['customer', 'lineItems.product'];
    }
}
```

```php
public function index(ListOrdersRequest $request)
{
    return Order::query()
        ->where('orders.team_id', $request->user()->team_id)
        ->apiFiltersAndSorts($request)
        ->get();
}
```

- `FilterableRequest` validates the shape of all four keys with the same rules and limits the package applies, and requires `first` and `last` together.
- `allowedRelations()` allows no relations unless you override it, and it covers both filters and sorts.
- `apiFiltersAndSorts($request)` applies the filters, then the sorts, then the window when both `first` and `last` were sent.
- `FilterableRequest` has no `authorize()`, and Laravel lets every request through a form request without one, so add your own.

### Adding your own operators

An operator is a class that implements `Dashworthy\ApiFilters\Contracts\FilterOperationContract`:

| Method | Job |
|---|---|
| `canHandle(FilterData $filterData)` | Says whether it applies this filter, usually by its `operator` |
| `rules()` | Returns the Laravel validation rules the value must pass |
| `handle(Builder $query, FilterData $filterData)` | Adds the filter's condition to `$query` |

List the class under `operations` in `config/api-filters.php`. Every class whose `canHandle()` accepts a filter checks the value against its `rules()`, then adds its condition, so two classes that handle the same operator both apply. An operator that no class handles gets a 422. To replace a built-in operator, take its class out of the list and add yours.

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

- **Columns.** A field must be a column the model exposes, matched in any letter case: one its `#[Filterable]` attribute lists or, without the attribute, any column of its table. Either way, a column the model hides (`$hidden`, or missing from a non-empty `$visible`) is out of reach, so `password` and `remember_token` cannot be filtered.
- **Without `#[Filterable]`, every visible column is filterable.** `$hidden` and `$visible` are then the boundary. A column you forget to hide can be probed one character at a time, for example with `password startsWith $2y$10$a`. Hide anything a client must not search, or list the safe columns with `#[Filterable]`.
- **Relations.** A dotted field works only through a path you allow-list. Clients never choose which method is called on your model.
- **Mistakes.** An unknown operator, a bad value or a field that cannot be used returns a 422 validation error keyed by its input path, for example `filter_sets.0.filters.1.field`. An unknown column and a hidden one get the same message.

### Limits

Every limit can be changed in `config/api-filters.php`:

| Limit | Key | Default |
|---|---|---|
| Filter sets per request | `limits.filter_sets` | 10 |
| Filters across all sets | `limits.filters` | 50 |
| Values in one `in` or `notIn` list | `limits.in_values` | 100 |
| Sorts per request | `limits.sorts` | 5 |
| Rows between `first` and `last` | `limits.window` | 1000 |

### Security model

| Threat | What the package does |
|---|---|
| A client's relation name calls a model method: `truncate.id` empties the table | A relation path is only looked up in your allow-list, and each listed segment was checked to be a relation method before it was ever called |
| A client probes a column, such as `password startsWith $2y$10$a` | Only the columns a model exposes can be filtered or sorted: the ones `#[Filterable]` lists, or else the columns of its table. Hidden columns never can, and a field must be a plain identifier |
| An `or` set returns other teams' rows | Each set, and each filter inside it, runs in a where group of its own |
| Input too large to run cheaply | Limits on filter sets, filters, `in` values, sorts and the window |
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
