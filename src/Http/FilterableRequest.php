<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\Http;

use Dashworthy\ApiFilters\ApiFilters;
use Dashworthy\ApiFilters\Parsing\FilterSetParser;
use Dashworthy\ApiFilters\Parsing\SortParser;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A form request for an endpoint that takes filter_sets, sorts and a first and
 * last window, validated with the same shape rules and limits the package
 * applies. Extend it, and override allowedRelations() to let clients filter
 * and sort through relations. Authorization stays with each endpoint's own
 * request.
 */
abstract class FilterableRequest extends FormRequest
{
    /**
     * The relation paths this endpoint lets clients filter and sort through.
     *
     * @return list<string>
     */
    public function allowedRelations(): array
    {
        return [];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $window = ApiFilters::windowRules($this->input('first'));

        return [
            ...FilterSetParser::shapeRules(),
            ...SortParser::shapeRules(),
            'first' => ['required_with:last', ...$window['first']],
            'last' => ['required_with:first', ...$window['last']],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [...FilterSetParser::shapeMessages(), ...SortParser::shapeMessages()];
    }
}
