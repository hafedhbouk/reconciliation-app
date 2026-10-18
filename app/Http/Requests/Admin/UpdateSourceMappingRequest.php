<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;

/**
 * Validation du formulaire de mapping d'une source.
 * 'mappings.*.source_column' : règle appliquée à chaque ligne du tableau mappings
 * (une ligne par champ interne, ex. mappings[amount][source_column]).
 * (Les Form Requests sont expliqués dans app/Http/Requests/BaseFormRequest.php.)
 */
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
