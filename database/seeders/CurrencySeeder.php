<?php

namespace Database\Seeders;

/**
 * Seed des devises supportées.
 *
 * Le champ decimal_places détermine la précision utilisée par
 * DecimalStringToMillimesTransform lors de la conversion des montants
 * en millimes.
 */
use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        Currency::query()->updateOrCreate(
            ['iso_code' => 'TND'],
            ['name' => 'Dinar Tunisien', 'symbol' => 'DT', 'decimal_places' => 3, 'is_active' => true]
        );

        Currency::query()->updateOrCreate(
            ['iso_code' => 'EUR'],
            ['name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2, 'is_active' => true]
        );

        Currency::query()->updateOrCreate(
            ['iso_code' => 'USD'],
            ['name' => 'Dollar Américain', 'symbol' => '$', 'decimal_places' => 2, 'is_active' => true]
        );
    }
}
