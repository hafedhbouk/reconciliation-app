<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;

class UpdateSourceMappingRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'mappings' => ['array'],
            'mappings.*.source_column' => ['nullable', 'string', 'max:255'],
            'mappings.*.is_required' => ['nullable', 'boolean'],
            'mappings.*.transform_type' => ['nullable', 'string'],
            'mappings.*.chars' => ['nullable', 'string', 'max:50'],
            'mappings.*.delimiter' => ['nullable', 'string', 'max:5'],
            'mappings.*.n' => ['nullable', 'integer', 'min:1'],
            'mappings.*.length' => ['nullable', 'integer', 'min:1'],
            'mappings.*.decimals' => ['nullable', 'integer', 'min:0', 'max:6'],
            'mappings.*.date_format' => ['nullable', 'string', 'max:50'],
        ];
    }
}
