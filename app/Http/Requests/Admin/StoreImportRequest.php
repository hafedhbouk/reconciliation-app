<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;
use App\Models\Source;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validation du formulaire de dépôt d'un fichier à importer.
 * (Les Form Requests sont expliqués dans app/Http/Requests/BaseFormRequest.php.)
 */
class StoreImportRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            // La source doit exister ET être active.
            'source_id' => ['required', Rule::exists('sources', 'id')->where('is_active', true)],
            // Defense-in-depth alongside the custom per-source extension
            // check below -- 'mimes' validates by real content-based mime
            // detection, not just the client-reported extension. 'txt' is
            // included because plain-delimited CSV exports (e.g. SMT's)
            // routinely content-sniff as text/plain rather than text/csv,
            // which 'mimes:csv' alone rejects even for a genuine .csv file.
            'file' => ['required', 'file', 'mimes:csv,txt,xls,xlsx'],
            'confirmed_duplicate' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Contrôle supplémentaire : l'extension du fichier doit correspondre au
     * type de la source (un CSV ne peut pas être déposé pour BNA, qui attend un Excel).
     */
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
