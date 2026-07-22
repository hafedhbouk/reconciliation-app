<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateCurrencyRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'iso_code' => ['required', 'string', 'size:3', Rule::unique('currencies', 'iso_code')->ignore($this->route('currency'))],
            'name' => ['required', 'string', 'max:255'],
            'symbol' => ['nullable', 'string', 'max:10'],
            'decimal_places' => ['required', 'integer', 'min:0', 'max:6'],
            'is_active' => ['boolean'],
        ];
    }
}
