<?php

namespace Database\Seeders;

use App\Models\MatchingRule;
use App\Models\Source;
use Illuminate\Database\Seeder;

/**
 * Seed des règles de rapprochement automatique par paires de sources.
 *
 * Règles configurées et champs comparés :
 * - ALPHA - BNA : num_autorisation, montant, date
 * - SMT - BNA : montant, date
 * - WEB - BNA : num_autorisation, montant, date
 * - ALPHA - WEB : reference, num_autorisation, montant, date
 * - ALPHA - SMT : montant, date
 * - WEB - SMT : montant, date
 * - ALPHA - STEG : reference, num_autorisation, montant, date
 * - STEG - BNA : num_autorisation, montant, date
 * - STEG - SMT : montant, date
 *
 * Chaque règle utilise :
 * - primary_key : champ(s) de regroupement des candidats
 * - verify_fields : champs secondaires à vérifier après le regroupement
 * - excluded_b : statuts bruts à exclure côté B (ex: Commission pour BNA)
 */
class MatchingRuleSeeder extends Seeder
{
    public function run(): void
    {
        $sourceIds = Source::query()->whereIn('code', ['ALPHA', 'BNA', 'WEB', 'SMT', 'STEG'])->pluck('id', 'code');

        $rules = [
            [
                'name' => 'ALPHA - BNA',
                'a' => 'ALPHA',
                'b' => 'BNA',
                'priority' => 10,
                'excluded_b' => ['Commission'],
                'primary_key' => ['a' => 'num_autorisation', 'b' => 'num_autorisation'],
                'verify_fields' => ['amount', 'date'],
            ],
            [
                'name' => 'SMT - BNA',
                'a' => 'SMT',
                'b' => 'BNA',
                'priority' => 20,
                'excluded_b' => ['Commission'],
                'primary_key' => ['a' => 'date|amount', 'b' => 'date|amount'],
                'verify_fields' => [],
            ],
            [
                'name' => 'WEB - BNA',
                'a' => 'WEB',
                'b' => 'BNA',
                'priority' => 30,
                'excluded_b' => ['Commission'],
                'primary_key' => ['a' => 'secondary_reference', 'b' => 'num_autorisation'],
                'verify_fields' => ['amount', 'date'],
            ],
            [
                'name' => 'ALPHA - WEB',
                'a' => 'ALPHA',
                'b' => 'WEB',
                'priority' => 40,
                'excluded_b' => [],
                'primary_key' => ['a' => 'reference', 'b' => 'reference'],
                'verify_fields' => [
                    ['a' => 'num_autorisation', 'b' => 'secondary_reference'],
                    'amount',
                    'date',
                ],
            ],
            [
                'name' => 'ALPHA - SMT',
                'a' => 'ALPHA',
                'b' => 'SMT',
                'priority' => 50,
                'excluded_b' => [],
                'primary_key' => ['a' => 'date|amount', 'b' => 'date|amount'],
                'verify_fields' => [],
            ],
            [
                'name' => 'WEB - SMT',
                'a' => 'WEB',
                'b' => 'SMT',
                'priority' => 60,
                'excluded_b' => [],
                'primary_key' => ['a' => 'date|amount', 'b' => 'date|amount'],
                'verify_fields' => [],
            ],
            [
                'name' => 'ALPHA - STEG',
                'a' => 'ALPHA',
                'b' => 'STEG',
                'priority' => 70,
                'excluded_b' => [],
                'primary_key' => ['a' => 'reference', 'b' => 'reference'],
                'verify_fields' => [
                    ['a' => 'num_autorisation', 'b' => 'secondary_reference'],
                    'amount',
                    'date',
                ],
            ],
            [
                'name' => 'STEG - BNA',
                'a' => 'STEG',
                'b' => 'BNA',
                'priority' => 80,
                'excluded_b' => ['Commission'],
                'primary_key' => ['a' => 'secondary_reference', 'b' => 'num_autorisation'],
                'verify_fields' => ['amount', 'date'],
            ],
            [
                'name' => 'STEG - SMT',
                'a' => 'STEG',
                'b' => 'SMT',
                'priority' => 90,
                'excluded_b' => [],
                'primary_key' => ['a' => 'date|amount', 'b' => 'date|amount'],
                'verify_fields' => [],
            ],
        ];

        foreach ($rules as $rule) {
            MatchingRule::query()->updateOrCreate(
                ['name' => $rule['name']],
                [
                    'source_a_id' => $sourceIds[$rule['a']],
                    'source_b_id' => $sourceIds[$rule['b']],
                    'cardinality' => 'N:M',
                    'priority' => $rule['priority'],
                    'is_active' => true,
                    'criteria' => [
                        'tolerance_amount_millimes' => 0,
                        'tolerance_days' => 0,
                        'excluded_status_raw' => ['a' => [], 'b' => $rule['excluded_b']],
                        'primary_key' => $rule['primary_key'],
                        'verify_fields' => $rule['verify_fields'],
                    ],
                ]
            );
        }
    }
}
