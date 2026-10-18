<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;

/**
 * Validation du formulaire de création d'une devise (code ISO de 3 lettres, unique).
 * (Les Form Requests sont expliqués dans app/Http/Requests/BaseFormRequest.php.)
 */
class StoreCurrencyRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'iso_code' => ['required', 'string', 'size:3', 'unique:currencies,iso_code'],
            'name' => ['required', 'string', 'max:255'],
            'symbol' => ['nullable', 'string', 'max:10'],
            'decimal_places' => ['required', 'integer', 'min:0', 'max:6'],
            'is_active' => ['boolean'],
        ];
    }
}
