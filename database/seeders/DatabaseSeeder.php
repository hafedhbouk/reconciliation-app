<?php

namespace Database\Seeders;

/**
 * Point d'entrée des seeds de l'application.
 *
 * Orchestre l'exécution ordonnée des seeders de référence, utilisateurs,
 * devises, banques, sources, mappings et règles de rapprochement.
 *
 * ---------------------------------------------------------------------------
 * GUIDE DÉBUTANT : qu'est-ce qu'un « seeder » ? (valable pour database/seeders)
 * ---------------------------------------------------------------------------
 * Un seeder REMPLIT la base avec les données nécessaires au démarrage (rôles,
 * compte administrateur, sources ALPHA/BNA/WEB/SMT, mappings, règles…).
 * Les migrations créent les tables vides ; les seeders y mettent les données de base.
 *   php artisan db:seed     exécute ce fichier, qui appelle les autres dans l'ordre
 *                           ci-dessous (l'ordre compte : il faut des rôles avant
 *                           l'administrateur, des banques avant les sources…).
 * Les seeders utilisent updateOrCreate / firstOrCreate : on peut les relancer
 * sans créer de doublons (on dit qu'ils sont « idempotents »).
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
