<?php

namespace App\Http\Requests\Admin;

use App\Enums\ExceptionStatus;
use App\Enums\ExceptionType;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation de la modification d'une exception.
 * 'sometimes' : la règle ne s'applique que si le champ est envoyé (chaque formulaire
 * de la page n'envoie qu'une partie des champs).
 * (Les Form Requests sont expliqués dans app/Http/Requests/BaseFormRequest.php.)
 */
class UpdateExceptionRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::in(array_column(ExceptionStatus::cases(), 'value'))],
            'type' => ['sometimes', Rule::in(array_column(ExceptionType::cases(), 'value'))],
            'assigned_to' => ['sometimes', 'nullable', 'exists:users,id'],
            'resolution_comment' => ['sometimes', 'nullable', 'string'],
            'is_expected' => ['sometimes', 'boolean'],
        ];
    }
}
