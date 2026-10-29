---
name: api-filters-development
description: >
  Apply dashworthy/api-filters in a Laravel app: filter and sort Eloquent
  queries from API request input (filter_sets, sorts, first, last), allow-list
  the relations clients may reach, take a whole request through a
  FilterableRequest, and add custom filter operations.
license: MIT
metadata:
  author: Andrew Leach
---

# API Filters

Use this skill when an endpoint filters, sorts or pages an Eloquent query from client input with `dashworthy/api-filters`.

## Workflow

### 1. Take the request through a FilterableRequest

```php
use Dashworthy\ApiFilters\Http\FilterableRequest;

class ListOrdersRequest extends FilterableRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Order::class);
    }

    /**
     * @return list<string>
     */
    public function allowedRelations(): array
    {
        return ['customer', 'lineItems.product'];
    }
}
```

- `FilterableRequest` validates the shape of `filter_sets`, `sorts`, `first` and `last`. It has no `authorize()`, so always add one.
- `allowedRelations()` returns relation method names joined by dots. A path also allows each shorter path it starts with. It allows nothing unless overridden.

### 2. Apply it after your own constraints

```php
public function index(ListOrdersRequest $request)
{
    return Order::query()
        ->where('orders.team_id', $request->user()->team_id)
        ->apiFiltersAndSorts($request)
        ->get();
}
```

Without a form request, use the builder macros or the fluent engine:

```php
Order::query()->apiFilters($filterSets, allowedRelations: ['customer']);
Order::query()->apiSorts($sorts, allowedRelations: ['customer']);
ApiFilters::for($query)->allowedRelations(['customer'])->filter($filterSets)->sort($sorts)->window(0, 50);
```

### 3. Add an operator only through api-filters.operations

A custom operator is a class implementing `Dashworthy\ApiFilters\Contracts\FilterOperationContract`: `canHandle(FilterData)`, `rules()` and `handle(Builder $query, FilterData $filterData)`. List it under `operations` in the published `config/api-filters.php`. The package wraps `handle()` in the filter's own where group, and in the relation's `has()` for a relation field.

## Rules

- Never pass a client-supplied string to Eloquent as a relation name (`whereHas`, `has`, `with`, `orderByPowerJoins`). Client relation paths must go through `allowedRelations()`.
- Hide every column clients must not search with `$hidden` or `$visible`. Every visible column is filterable and sortable.
- Qualify your own `where` columns (`orders.team_id`) on queries that sort through relations, because relation sorts join the related table.
- In a custom operation, bind every value and wrap the column with the grammar. Never write client input into SQL. Cap value sizes in `rules()`.
- Sorts only reach to-one relations. Do not allow-list a path for sorting through a has-many.

## Anti-patterns

- Listing a relation in `allowedRelations()` whose related model exposes columns clients must not see.
- Adding a custom operation that ignores `rules()` and reads `$filterData->value` unchecked.
