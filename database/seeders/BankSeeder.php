<?php

namespace Database\Seeders;

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
