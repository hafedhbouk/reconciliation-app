<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateHolidayRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'holiday_date' => [
                'required',
                'date',
                Rule::unique('holidays', 'holiday_date')
                    ->where(fn ($query) => $query->where('country_code', $this->input('country_code')))
                    ->ignore($this->route('holiday')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'country_code' => ['required', 'string', 'size:2'],
            'is_recurring_yearly' => ['boolean'],
        ];
    }
}
