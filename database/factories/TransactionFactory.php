<?php

namespace Database\Factories;

use App\Models\Source;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'source_id' => Source::factory(),
            'external_reference' => (string) fake()->unique()->numerify('######'),
            'transaction_date' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'amount_millimes' => fake()->numberBetween(1000, 500000),
            'raw_payload' => [],
        ];
    }
}
