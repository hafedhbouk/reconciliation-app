<?php

namespace App\Http\Requests\Admin;

use App\Enums\MatchingCardinality;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateMatchingRuleRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'source_a_id' => ['required', 'exists:sources,id'],
            'source_b_id' => ['required', 'exists:sources,id', 'different:source_a_id'],
            'cardinality' => ['required', Rule::in(array_column(MatchingCardinality::cases(), 'value'))],
            'priority' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'tolerance_amount_millimes' => ['required', 'integer', 'min:0'],
            'tolerance_days' => ['required', 'integer', 'min:0'],
            'excluded_status_raw_a' => ['nullable', 'string', 'max:255'],
            'excluded_status_raw_b' => ['nullable', 'string', 'max:255'],
        ];
    }
}
