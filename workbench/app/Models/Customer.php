<?php

namespace Workbench\App\Models;

use Dashworthy\ApiFilters\Attributes\Filterable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Workbench\Database\Factories\CustomerFactory;

/**
 * A customer that lists the password column it hides, so the tests show that
 * hiding a column wins over listing it.
 */
#[Filterable(['name', 'email', 'password'])]
#[Hidden(['password'])]
#[Unguarded]
#[UseFactory(CustomerFactory::class)]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
