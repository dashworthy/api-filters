<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Workbench\Database\Factories\CountryFactory;

#[Unguarded]
#[UseFactory(CountryFactory::class)]
class Country extends Model
{
    /** @use HasFactory<CountryFactory> */
    use HasFactory;
}
