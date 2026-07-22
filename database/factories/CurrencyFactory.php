<?php

namespace Database\Factories;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Currency>
 */
class CurrencyFactory extends Factory
{
    protected $model = Currency::class;

    public function definition(): array
    {
        return [
            'iso_code' => strtoupper(fake()->unique()->lexify('???')),
            'name' => fake()->currencyCode(),
            'symbol' => fake()->currencyCode(),
            'decimal_places' => 3,
            'is_active' => true,
        ];
    }
}
