<?php

namespace Workbench\App\Models;

use Dashworthy\ApiFilters\Attributes\Filterable;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Workbench\Database\Factories\CountryFactory;

#[Filterable(['name'])]
#[Unguarded]
#[UseFactory(CountryFactory::class)]
class Country extends Model
{
    /** @use HasFactory<CountryFactory> */
    use HasFactory;
}
