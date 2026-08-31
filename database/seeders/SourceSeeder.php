<?php

namespace Database\Seeders;

use App\Models\Bank;
use App\Models\Currency;
use App\Models\Source;
use Illuminate\Database\Seeder;

/**
 * Seed des sources de données métier (banques et passerelles de paiement).
 *
 * Sources configurables (4 fiches) :
 * - ALPHA : fichier XLSX d'encaissements
 * - BNA : relevé bancaire XLSX
 * - WEB/STEG : portail STEG au format CSV (même source, alias WEB)
 * - SMT : export passerelle SMT au format CSV
 */
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
                'name' => 'WEB / STEG',
                'file_type' => 'csv',
                'default_currency_id' => $tnd?->id,
                'is_active' => true,
                'description' => 'Portail de paiement en ligne STEG (alias WEB). Session, reference, montant, date_paiement, recu_paie.',
                'config' => ['csv_delimiter' => ','],
            ]
        );

        Source::query()->updateOrCreate(
            ['code' => 'SMT'],
            [
                'name' => 'SMT',
                'file_type' => 'csv',
                'default_currency_id' => $tnd?->id,
                'is_active' => true,
                'description' => 'Export SMT — passerelle de paiement, en-têtes accentués mangled en "?" dans le fichier réel (Identifiant de la réponse d\'autorisation, Numéro de référence, Montant, New Deposit date, Devise, Etat du paiement).',
                'config' => ['csv_delimiter' => ';'],
            ]
        );
    }
}
