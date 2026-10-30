<?php

namespace Workbench\App\Models\Concerns;

use Dashworthy\ApiFilters\Attributes\Filterable;

/**
 * Lists status as the only column api-filters exposes on a model that uses it.
 */
#[Filterable(['status'])]
trait ExposesStatus {}
