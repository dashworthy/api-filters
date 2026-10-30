---
name: api-filters-development
description: >
  Apply dashworthy/api-filters in a Laravel app: filter and sort Eloquent
  queries from API request input (filter_sets, sorts) through a
  FilterableRequest, list the columns and relation paths a model exposes
  with #[Filterable], and add custom filter operations.
license: MIT
metadata:
  author: Andrew Leach
---

# API Filters

Use this skill when an endpoint filters or sorts an Eloquent query from client input with `dashworthy/api-filters`.

## Workflow

### 1. List what clients may use on the model

```php
use Dashworthy\ApiFilters\Attributes\Filterable;

#[Filterable(
    columns: ['id', 'status', 'total', 'created_at'],
    relations: ['customer', 'lineItems.product'],
)]
class Order extends Model
{
}
```

- `columns` are the columns clients may filter and sort by, including `id` or `created_at` when clients use them. A model without the attribute exposes nothing. A listed column the model hides with `$hidden` or `$visible` stays out of reach.
- `relations` are full paths from this model: relation method names joined by dots. A path also allows each shorter path it starts with. Only the queried model's `relations` count, so list `customer.country` on `Order` rather than `country` on `Customer`.
- Every model a path reaches needs its own `#[Filterable]` listing the `columns` clients may use through it.

### 2. Take the request through a FilterableRequest

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

`FilterableRequest` checks the shape and limits of `filter_sets` and `sorts`, each filter's operator, and its value against the operation's `rules()`, before the controller runs. It does not know the model. It has no `authorize()`, so always add one.

### 3. Apply it after your own constraints

```php
public function index(ListOrdersRequest $request)
{
    return Order::query()
        ->where('orders.team_id', $request->user()->team_id)
        ->apiFiltersAndSorts($request)
        ->get();
}
```

`apiFiltersAndSorts()` is the only entry point. It checks each field against the query's model, returns a 422 for one the model does not expose, then applies the filters and the sorts.

### 4. Add an operator only through api-filters.operations

A custom operator is a class implementing `Dashworthy\ApiFilters\Contracts\FilterOperationContract`: `canHandle(FilterData)`, `rules()` and `handle(Builder $query, FilterData $filterData)`. List it under `operations` in the published `config/api-filters.php`. The package wraps `handle()` in the filter's own where group, and in the relation's `has()` for a relation field.

## Rules

- Never pass a client-supplied string to Eloquent as a relation name (`whereHas`, `has`, `with`, `orderByPowerJoins`). Client relation paths must go through the `relations` of `#[Filterable]`.
- No column can be filtered or sorted through a morph-to relation, because the model it reaches differs from row to row. `doesntHave` still works on one.
- Qualify your own `where` columns (`orders.team_id`) on queries that sort through relations, because relation sorts join the related table.
- In a custom operation, bind every value and wrap the column with the grammar. Never write client input into SQL. Cap value sizes in `rules()`.
- Sorts only reach to-one relations. A listed has-many path can be filtered through, never sorted by.

## Anti-patterns

- Listing a relation path whose related model exposes columns clients must not see.
- Adding a custom operation that ignores `rules()` and reads `$filterData->value` unchecked.
