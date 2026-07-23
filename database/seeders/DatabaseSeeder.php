<?php

namespace Database\Seeders;

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
