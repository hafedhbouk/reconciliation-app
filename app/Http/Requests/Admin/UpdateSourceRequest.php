<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

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
