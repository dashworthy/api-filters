<?php

namespace Workbench\App\Models;

use Dashworthy\ApiFilters\Attributes\Filterable;

/**
 * A customer that exposes only its name to api-filters.
 */
#[Filterable(['name'])]
class ListedCustomer extends Customer
{
    protected $table = 'customers';
}
