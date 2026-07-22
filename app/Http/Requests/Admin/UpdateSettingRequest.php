<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;

class UpdateSettingRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'value' => ['required'],
        ];
    }
}
