<?php

namespace Database\Seeders;

use App\Models\Bank;
use App\Models\Currency;
use App\Models\Source;
use Illuminate\Database\Seeder;

class SourceSeeder extends Seeder
{
    public function run(): void
    {
        $tnd = Currency::query()->where('iso_code', 'TND')->first();
        $bna = Bank::query()->where('code', 'BNA')->first();
        $alpha = Bank::query()->where('code', 'ALPHA')->first();

        Source::query()->updateOrCreate(
            ['code' => 'ALPHA'],
            [
                'name' => 'Alpha',
                'bank_id' => $alpha?->id,
                'file_type' => 'xlsx',
                'default_currency_id' => $tnd?->id,
                'is_active' => true,
                'description' => 'Encaissements Alpha (REFERENCE, MONTANT_ENCAISS, DAT_ENC, NUM_AUTO, CANAL).',
            ]
        );

        Source::query()->updateOrCreate(
            ['code' => 'BNA'],
            [
                'name' => 'BNA',
                'bank_id' => $bna?->id,
                'file_type' => 'xlsx',
                'default_currency_id' => $tnd?->id,
                'is_active' => true,
                'description' => 'Relevé BNA (Numéro du terminal, N° autorisation, Type de transaction, Date, Montant).',
            ]
        );

        Source::query()->updateOrCreate(
            ['code' => 'WEB'],
            [
                'name' => 'WEB',
                'file_type' => 'csv',
                'default_currency_id' => $tnd?->id,
                'is_active' => true,
                'description' => 'Export de la plateforme de paiement en ligne (Order type, Acquéreur, Montant, Devise, Numéro de carte, Numéro de référence).',
            ]
        );

        Source::query()->updateOrCreate(
            ['code' => 'SMT'],
            [
                'name' => 'SMT',
                'file_type' => 'csv',
                'default_currency_id' => $tnd?->id,
                'is_active' => true,
                'description' => 'Export SMT (session, reference, montant, date_paiement, recu_paie, valid_oper).',
            ]
        );

        Source::query()->updateOrCreate(
            ['code' => 'STEG'],
            [
                'name' => 'STEG',
                'file_type' => 'csv',
                'default_currency_id' => $tnd?->id,
                'is_active' => false,
                'description' => 'Règles définies mais non vérifiées : aucun fichier d\'exemple fourni à ce jour.',
            ]
        );
    }
}
