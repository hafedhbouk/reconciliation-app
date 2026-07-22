<?php

namespace Database\Factories;

use App\Models\Bank;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bank>
 */
class BankFactory extends Factory
{
    protected $model = Bank::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('????')),
            'name' => fake()->company(),
            'swift_code' => strtoupper(fake()->lexify('???????')),
            'is_active' => true,
            'notes' => null,
        ];
    }
}
