<?php

namespace Database\Factories;

use App\Models\MatchingRule;
use App\Models\Source;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MatchingRule>
 */
class MatchingRuleFactory extends Factory
{
    protected $model = MatchingRule::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'source_a_id' => Source::factory(),
            'source_b_id' => Source::factory(),
            'cardinality' => 'N:M',
            'priority' => 0,
            'is_active' => true,
            'criteria' => [
                'tolerance_amount_millimes' => 0,
                'tolerance_days' => 0,
                'excluded_status_raw' => ['a' => [], 'b' => []],
            ],
        ];
    }
}
