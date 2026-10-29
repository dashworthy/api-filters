<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\Visible;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Workbench\Database\Factories\ProfileFactory;

#[Visible(['id', 'display_name'])]
#[Unguarded]
#[UseFactory(ProfileFactory::class)]
class Profile extends Model
{
    /** @use HasFactory<ProfileFactory> */
    use HasFactory;
}
