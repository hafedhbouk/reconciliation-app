<?php

namespace Database\Factories;

use App\Models\Source;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Source>
 */
class SourceFactory extends Factory
{
    protected $model = Source::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('?????')),
            'name' => fake()->word(),
            'bank_id' => null,
            'file_type' => fake()->randomElement(['csv', 'xls', 'xlsx']),
            'default_currency_id' => null,
            'is_active' => true,
            'description' => null,
            'config' => null,
        ];
    }
}
