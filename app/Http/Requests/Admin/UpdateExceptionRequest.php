<?php

namespace App\Http\Requests\Admin;

use App\Enums\ExceptionStatus;
use App\Enums\ExceptionType;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateExceptionRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::in(array_column(ExceptionStatus::cases(), 'value'))],
            'type' => ['sometimes', Rule::in(array_column(ExceptionType::cases(), 'value'))],
            'assigned_to' => ['sometimes', 'nullable', 'exists:users,id'],
            'resolution_comment' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
