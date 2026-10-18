<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;

/**
 * Validation de la création d'une source (code unique, type de fichier csv/xls/xlsx).
 * (Les Form Requests sont expliqués dans app/Http/Requests/BaseFormRequest.php.)
 */
class StoreSourceRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'unique:sources,code'],
            'name' => ['required', 'string', 'max:255'],
            'bank_id' => ['nullable', 'exists:banks,id'],
            'file_type' => ['required', 'in:csv,xls,xlsx'],
            'default_currency_id' => ['nullable', 'exists:currencies,id'],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string'],
        ];
    }
}
