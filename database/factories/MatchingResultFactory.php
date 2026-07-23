<?php

namespace Database\Factories;

use App\Enums\MatchingResultStatus;
use App\Models\MatchingResult;
use App\Models\MatchingRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MatchingResult>
 */
class MatchingResultFactory extends Factory
{
    protected $model = MatchingResult::class;

    public function definition(): array
    {
        return [
            'matching_rule_id' => MatchingRule::factory(),
            'batch_reference' => (string) fake()->uuid(),
            'status' => MatchingResultStatus::Matched->value,
            'confidence_score' => 100.00,
            'matched_by' => null,
            'matched_at' => now(),
            'notes' => null,
        ];
    }
}
