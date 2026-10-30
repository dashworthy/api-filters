<?php

namespace Workbench\App\Models;

use Dashworthy\ApiFilters\Attributes\Filterable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An order whose own list replaces Order's, one of its columns the
 * internal_reference column that Order hides.
 */
#[Filterable(['status', 'total', 'internal_reference'])]
class ListedOrder extends Order
{
    protected $table = 'orders';

    /**
     * @return BelongsTo<ListedCustomer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(ListedCustomer::class, 'customer_id');
    }
}
