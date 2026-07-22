<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;

class StoreBankRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'unique:banks,code'],
            'name' => ['required', 'string', 'max:255'],
            'swift_code' => ['nullable', 'string', 'max:20'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
