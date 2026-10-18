<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation du formulaire de modification d'une banque.
 * Rule::unique(...)->ignore(...) : le code reste unique, sans compter la banque modifiée elle-même.
 * (Les Form Requests sont expliqués dans app/Http/Requests/BaseFormRequest.php.)
 */
class UpdateBankRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('banks', 'code')->ignore($this->route('bank'))],
            'name' => ['required', 'string', 'max:255'],
            'swift_code' => ['nullable', 'string', 'max:20'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
