<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\Attributes;

use Attribute;

/**
 * Lists what clients may filter and sort a model by: its columns, and the
 * relation paths a query on it may reach, written as relation method names
 * joined by dots. A model without it exposes nothing, and a listed column the
 * model hides stays out of reach. The model, one of its traits or a parent
 * class may carry it, as with Laravel's #[Hidden].
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Filterable
{
    /**
     * @param  list<string>  $columns
     * @param  list<string>  $relations
     */
    public function __construct(public array $columns, public array $relations = []) {}
}
