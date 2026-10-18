<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;

/**
 * Validation de la modification d'un paramètre : la valeur est obligatoire
 * (sa conversion selon le type est faite par SettingController::castValue()).
 * (Les Form Requests sont expliqués dans app/Http/Requests/BaseFormRequest.php.)
 */
class UpdateSettingRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'value' => ['required'],
        ];
    }
}
