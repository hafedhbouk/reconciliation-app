<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;

/**
 * Validation de la création d'un rôle : nom unique, permissions existantes.
 * (Les Form Requests sont expliqués dans app/Http/Requests/BaseFormRequest.php.)
 */
class StoreRoleRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'permissions' => ['array'],
            'permissions.*' => ['exists:permissions,name'],
        ];
    }
}
