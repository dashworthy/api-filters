<?php

namespace Workbench\App\Models;

use Dashworthy\ApiFilters\Attributes\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A comment whose commentable relation reaches a different model from row to
 * row. It lists created_at, a name its commentables' tables also have.
 */
#[Filterable(['body', 'created_at'], relations: ['commentable'])]
class Comment extends Model
{
    /**
     * @return MorphTo<Model, $this>
     */
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }
}
