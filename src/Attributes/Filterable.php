<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\Attributes;

use Attribute;

/**
 * Lists the columns of a model that clients may filter and sort by, in place
 * of every column the model does not hide. A listed column the model hides
 * stays out of reach. The model, one of its traits or a parent class may
 * carry it, as with Laravel's #[Hidden].
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Filterable
{
    /**
     * @param  list<string>  $columns
     */
    public function __construct(public array $columns) {}
}
