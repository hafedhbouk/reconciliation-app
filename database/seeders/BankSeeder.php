<?php

namespace Database\Seeders;

/**
 * Seed des banques connues de l'application.
 *
 * Utilise updateOrCreate sur le code pour être idempotent : ré-exécuter
 * le seeder ne crée pas de doublons.
 */
use App\Models\Bank;
use Illuminate\Database\Seeder;

class BankSeeder extends Seeder
{
    public function run(): void
    {
        Bank::query()->updateOrCreate(
            ['code' => 'BNA'],
            ['name' => 'Banque Nationale Agricole', 'is_active' => true]
        );

        Bank::query()->updateOrCreate(
            ['code' => 'ALPHA'],
            ['name' => 'Alpha', 'is_active' => true]
        );
    }
}
