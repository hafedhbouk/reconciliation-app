<?php

namespace App\Http\Requests\Admin;

use App\Enums\MatchingStatus;
use App\Http\Requests\BaseFormRequest;
use App\Models\NormalizedTransaction;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;

class StoreManualMatchRequest extends BaseFormRequest
{
    public function rules(): array
    {
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

            $unmatchedCount = 0;
            foreach ($allIds->chunk(1000) as $chunk) {
                $unmatchedCount += NormalizedTransaction::query()
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
