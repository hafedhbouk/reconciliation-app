<?php

namespace Database\Seeders;

use App\Models\MatchingRule;
use App\Models\Source;
use Illuminate\Database\Seeder;

/**
 * Seeds the 6 pairwise matching rules the client's spec lists explicitly
 * (STEG excluded -- no verified sample data yet, same boundary as
 * SourceColumnMappingSeeder). All rules use zero tolerance and N:M
 * cardinality (descriptive only, never a hard reject -- see RuleMatcher's
 * cardinalityNote()): real data supports exact reference/amount/date
 * matching, and ALPHA itself has verified internal reference duplication
 * that a strict 1:1 rule would strand as unmatched forever. Priority puts
 * the best-verified, highest-volume BNA pairs first; BNA-involving rules
 * exclude its "Commission" rows (a fee row, not a transaction, sharing its
 * reference with a paired sale) from the candidate pool.
 */
class MatchingRuleSeeder extends Seeder
{
    public function run(): void
    {
        $sourceIds = Source::query()->whereIn('code', ['ALPHA', 'BNA', 'WEB', 'SMT'])->pluck('id', 'code');

        $rules = [
            ['name' => 'ALPHA ↔ BNA', 'a' => 'ALPHA', 'b' => 'BNA', 'priority' => 10, 'excluded_b' => ['Commission']],
            ['name' => 'SMT ↔ BNA', 'a' => 'SMT', 'b' => 'BNA', 'priority' => 20, 'excluded_b' => ['Commission']],
            ['name' => 'WEB ↔ BNA', 'a' => 'WEB', 'b' => 'BNA', 'priority' => 30, 'excluded_b' => ['Commission']],
            ['name' => 'ALPHA ↔ WEB', 'a' => 'ALPHA', 'b' => 'WEB', 'priority' => 40, 'excluded_b' => []],
            ['name' => 'ALPHA ↔ SMT', 'a' => 'ALPHA', 'b' => 'SMT', 'priority' => 50, 'excluded_b' => []],
            ['name' => 'WEB ↔ SMT', 'a' => 'WEB', 'b' => 'SMT', 'priority' => 60, 'excluded_b' => []],
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
                    ],
                ]
            );
        }
    }
}
