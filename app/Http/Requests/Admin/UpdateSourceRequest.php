<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation de la modification d'une source (voir UpdateBankRequest pour ->ignore()).
 * (Les Form Requests sont expliqués dans app/Http/Requests/BaseFormRequest.php.)
 */
class UpdateSourceRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('sources', 'code')->ignore($this->route('source'))],
            'name' => ['required', 'string', 'max:255'],
            'bank_id' => ['nullable', 'exists:banks,id'],
            'file_type' => ['required', 'in:csv,xls,xlsx'],
            'default_currency_id' => ['nullable', 'exists:currencies,id'],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string'],
        ];
    }
}
