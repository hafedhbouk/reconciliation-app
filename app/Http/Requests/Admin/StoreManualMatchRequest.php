<?php

namespace App\Http\Requests\Admin;

use App\Enums\MatchingStatus;
use App\Http\Requests\BaseFormRequest;
use App\Models\NormalizedTransaction;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;

/**
 * Validation d'un rapprochement manuel : au moins une transaction de chaque
 * côté, aucune transaction des deux côtés à la fois, et toutes encore non rapprochées.
 * (Les Form Requests sont expliqués dans app/Http/Requests/BaseFormRequest.php.)
 */
class StoreManualMatchRequest extends BaseFormRequest
{
    public function rules(): array
    {
        // 'array', 'min:1' : une liste d'au moins un élément ; '.*' => 'distinct' : pas de doublon dans la liste.
        return [
            'normalized_transaction_ids_a' => ['required', 'array', 'min:1'],
            'normalized_transaction_ids_a.*' => ['integer', 'distinct'],
            'normalized_transaction_ids_b' => ['required', 'array', 'min:1'],
            'normalized_transaction_ids_b.*' => ['integer', 'distinct'],
        ];
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            $idsA = collect($this->input('normalized_transaction_ids_a', []));
            $idsB = collect($this->input('normalized_transaction_ids_b', []));

            if ($idsA->intersect($idsB)->isNotEmpty()) {
                $validator->errors()->add('normalized_transaction_ids_b', __('Une même transaction ne peut pas être sélectionnée des deux côtés.'));

                return;
            }

            $allIds = $idsA->merge($idsB);

            // On compte, par paquets de 1 000 id, combien sont encore non rapprochées :
            // toutes doivent l'être (le contrôleur refait la vérification sous verrou).
            $unmatchedCount = 0;
            foreach ($allIds->chunk(1000) as $chunk) {
                $unmatchedCount += NormalizedTransaction::query()
                    ->fromActiveImports()
                    ->whereIn('id', $chunk)
                    ->where('matching_status', MatchingStatus::Unmatched->value)
                    ->count();
            }

            if ($unmatchedCount !== $allIds->count()) {
                $validator->errors()->add('normalized_transaction_ids_a', __('Une ou plusieurs transactions sélectionnées ne sont plus disponibles (déjà rapprochées entre-temps).'));
            }
        });
    }
}
