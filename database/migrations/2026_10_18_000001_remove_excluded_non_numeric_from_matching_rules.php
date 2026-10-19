<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Retire l'option excluded_non_numeric des critères des règles enregistrées.
 *
 * Elle écartait toute autorisation ou tout reçu contenant une lettre
 * (ex. M2K5RA), alors que c'est un code valide présent tel quel dans ALPHA,
 * BNA et WEB : ces transactions restaient « non rapprochées » à tort.
 * Le moteur ne lit plus cette option ; on la supprime aussi de la base.
 * Idempotente : ne réécrit que les règles qui contiennent encore la clé.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('matching_rules')->orderBy('id')->get(['id', 'criteria'])->each(function ($rule) {
            $criteria = json_decode((string) $rule->criteria, true);
            if (! is_array($criteria) || ! array_key_exists('excluded_non_numeric', $criteria)) {
                return;
            }

            unset($criteria['excluded_non_numeric']);
            DB::table('matching_rules')->where('id', $rule->id)
                ->update(['criteria' => json_encode($criteria, JSON_THROW_ON_ERROR)]);
        });
    }

    public function down(): void
    {
        // Data correction only; the removed exclusion was wrong.
    }
};
