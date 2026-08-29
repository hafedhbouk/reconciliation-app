<?php

namespace Database\Seeders;

/**
 * Point d'entrée des seeds de l'application.
 *
 * Orchestre l'exécution ordonnée des seeders de référence, utilisateurs,
 * devises, banques, sources, mappings et règles de rapprochement.
 */
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            AdminUserSeeder::class,
            CurrencySeeder::class,
            BankSeeder::class,
            SourceSeeder::class,
            SourceColumnMappingSeeder::class,
            SettingSeeder::class,
            MatchingRuleSeeder::class,
        ]);
    }
}
