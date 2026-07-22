<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['group' => 'matching', 'key' => 'tolerance_amount_millimes', 'value' => 0, 'type' => 'integer', 'description' => 'Tolérance de montant autorisée (en millimes) pour un rapprochement automatique.'],
            ['group' => 'matching', 'key' => 'tolerance_days', 'value' => 0, 'type' => 'integer', 'description' => 'Tolérance de jours autorisée entre deux dates pour un rapprochement automatique.'],
            ['group' => 'general', 'key' => 'date_format', 'value' => 'd/m/Y', 'type' => 'string', 'description' => 'Format d\'affichage des dates.'],
            ['group' => 'general', 'key' => 'amount_format', 'value' => '#,##0.000', 'type' => 'string', 'description' => 'Format d\'affichage des montants.'],
        ];

        foreach ($defaults as $default) {
            Setting::query()->updateOrCreate(
                ['group' => $default['group'], 'key' => $default['key']],
                ['value' => $default['value'], 'type' => $default['type'], 'description' => $default['description']]
            );
        }
    }
}
