<?php

namespace Workbench\App\Http\Requests;

use Dashworthy\ApiFilters\Http\FilterableRequest;

class ListOrdersRequest extends FilterableRequest
{
    /**
     * @return list<string>
     */
    public function allowedRelations(): array
    {
        return ['customer'];
    }
}
