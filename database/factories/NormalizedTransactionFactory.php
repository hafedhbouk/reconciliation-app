<?php

namespace Database\Factories;

use App\Enums\MatchingStatus;
use App\Models\NormalizedTransaction;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NormalizedTransaction>
 */
class NormalizedTransactionFactory extends Factory
{
    protected $model = NormalizedTransaction::class;

    public function definition(): array
    {
        return [
            'transaction_id' => Transaction::factory(),
            'normalized_reference' => (string) fake()->unique()->numerify('######'),
            'normalized_amount_millimes' => fake()->numberBetween(1000, 500000),
            'normalized_date' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'dedup_hash' => fake()->sha256(),
            'matching_status' => MatchingStatus::Unmatched->value,
        ];
    }
}
