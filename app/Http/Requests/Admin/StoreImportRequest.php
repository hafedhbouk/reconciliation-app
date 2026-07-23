<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;
use App\Models\Source;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreImportRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'source_id' => ['required', Rule::exists('sources', 'id')->where('is_active', true)],
            // Defense-in-depth alongside the custom per-source extension
            // check below -- 'mimes' validates by real content-based mime
            // detection, not just the client-reported extension.
            'file' => ['required', 'file', 'mimes:csv,xls,xlsx'],
            'confirmed_duplicate' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $source = Source::query()->find($this->input('source_id'));
            $file = $this->file('file');

            if (! $source || ! $file) {
                return;
            }

            $extension = strtolower($file->getClientOriginalExtension());
            $expected = $source->file_type === 'xls' || $source->file_type === 'xlsx'
                ? ['xls', 'xlsx']
                : [$source->file_type];

            if (! in_array($extension, $expected, true)) {
                $validator->errors()->add('file', __(
                    'Le fichier doit être de type :types pour la source sélectionnée.',
                    ['types' => implode('/', $expected)]
                ));
            }
        });
    }
}
