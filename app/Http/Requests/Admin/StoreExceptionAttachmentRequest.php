<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;

/**
 * Validation d'une pièce jointe d'exception : 10 Mo maximum (max:10240, en kilo-octets)
 * et seulement des types de documents courants (pas d'exécutable).
 * (Les Form Requests sont expliqués dans app/Http/Requests/BaseFormRequest.php.)
 */
class StoreExceptionAttachmentRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:10240',
                'mimes:pdf,png,jpg,jpeg,doc,docx,xls,xlsx,csv,txt',
            ],
        ];
    }
}
