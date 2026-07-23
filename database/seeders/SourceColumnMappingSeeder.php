<?php

namespace Database\Seeders;

use App\Models\Source;
use App\Models\SourceColumnMapping;
use Illuminate\Database\Seeder;

/**
 * Seeds the column mappings for ALPHA/BNA/WEB/SMT from column names and
 * cross-source value overlaps verified directly against the real sample
 * files (storage/app/samples/, not committed). One correction worth noting:
 * for ALPHA and SMT, the field each source's own documentation would call
 * "the reference" is NOT the field that actually correlates cross-source —
 * NUM_AUTO (ALPHA) and recu_paie (SMT), once their conditional leading b/B is
 * stripped, are 6-digit codes verified to overlap with BNA's N° autorisation
 * (98.7% and 100% of distinct values respectively) and with WEB's
 * "Identifiant de la réponse d'autorisation". Those are mapped as the core
 * `reference` field; each source's own internal reference becomes an
 * auxiliary `secondary_reference` instead.
 *
 * STEG has no sample file (client-confirmed). Its rows below are placeholders
 * built only from the client's written rules — source_column names are
 * best-guesses and MUST be revisited via the mapping association screen
 * (admin.sources.mappings.edit) the moment a real STEG file arrives.
 */
class SourceColumnMappingSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAlpha();
        $this->seedBna();
        $this->seedWeb();
        $this->seedSmt();
        $this->seedSteg();
    }

    private function seedAlpha(): void
    {
        $source = Source::query()->where('code', 'ALPHA')->firstOrFail();

        $this->upsert($source, 'reference', 'NUM_AUTO', [
            ['key' => 'trim'],
            ['key' => 'strip_prefix_chars', 'config' => ['chars' => ['B', 'b']]],
        ], required: true, order: 0);

        $this->upsert($source, 'secondary_reference', 'REFERENCE', [['key' => 'trim']], order: 1);

        $this->upsert($source, 'amount', 'MONTANT_ENCAISS', [
            ['key' => 'trim'],
            ['key' => 'fixed_width_millimes'],
        ], required: true, order: 2);

        $this->upsert($source, 'date', 'DAT_ENC', [
            ['key' => 'date_parse', 'config' => ['format' => 'd/m/Y', 'output' => 'date']],
        ], required: true, order: 3);

        $this->upsert($source, 'canal', 'CANAL', [['key' => 'trim']], order: 4);
    }

    private function seedBna(): void
    {
        $source = Source::query()->where('code', 'BNA')->firstOrFail();

        $this->upsert($source, 'reference', 'N° autorisation', [['key' => 'trim']], required: true, order: 0);

        $this->upsert($source, 'amount', 'Montant (TND)', [
            ['key' => 'trim'],
            ['key' => 'decimal_string_to_millimes', 'config' => ['decimals' => 3]],
        ], required: true, order: 1);

        $this->upsert($source, 'date', 'Date', [
            ['key' => 'date_parse', 'config' => ['format' => 'd/m/Y', 'output' => 'date']],
        ], required: true, order: 2);

        $this->upsert($source, 'status_raw', 'Type de la transaction', [['key' => 'trim']], order: 3);
    }

    private function seedWeb(): void
    {
        $source = Source::query()->where('code', 'WEB')->firstOrFail();

        // The real file's bytes have literal '?' (0x3F) wherever an accented character
        // belongs — this is baked into the source export itself (verified: neither
        // UTF-8 nor CP1252 decoding recovers the accents, so it isn't an encoding bug
        // we can fix on read). The mapping engine matches source_column by exact
        // literal text, so these two values MUST use the mangled form actually
        // present in the file, not the grammatically-correct French text.
        $this->upsert($source, 'reference', "Identifiant de la r?ponse d'autorisation", [['key' => 'trim']], required: true, order: 0);

        $this->upsert($source, 'secondary_reference', 'Num?ro de r?f?rence', [['key' => 'trim']], order: 1);

        $this->upsert($source, 'amount', 'Montant', [
            ['key' => 'trim'],
            ['key' => 'decimal_string_to_millimes', 'config' => ['decimals' => 3]],
        ], required: true, order: 2);

        $this->upsert($source, 'datetime', 'Date', [
            ['key' => 'date_parse', 'config' => ['format' => 'Y.m.d H:i:s', 'output' => 'datetime']],
        ], required: true, order: 3);

        $this->upsert($source, 'currency_code', 'Devise', [['key' => 'trim']], order: 4);
        $this->upsert($source, 'status_raw', 'Etat du paiement', [['key' => 'trim']], order: 5);
    }

    private function seedSmt(): void
    {
        $source = Source::query()->where('code', 'SMT')->firstOrFail();

        $this->upsert($source, 'reference', 'recu_paie', [
            ['key' => 'trim'],
            ['key' => 'strip_prefix_chars', 'config' => ['chars' => ['B', 'b']]],
        ], required: true, order: 0);

        $this->upsert($source, 'secondary_reference', 'reference', [['key' => 'trim']], order: 1);

        $this->upsert($source, 'amount', 'montant', [
            ['key' => 'trim'],
            ['key' => 'fixed_width_millimes'],
        ], required: true, order: 2);

        // Literal header text — a leftover SQL expression string in the real export.
        $this->upsert($source, 'date', "DATE_FORMAT(`date_au`, '%d%m%Y')", [
            ['key' => 'date_parse', 'config' => ['format' => 'dmY', 'output' => 'date']],
        ], required: true, order: 3);

        $this->upsert($source, 'datetime', 'date_paiement', [
            ['key' => 'date_parse', 'config' => ['format' => 'Y-m-d H:i:s', 'output' => 'datetime']],
        ], order: 4);

        $this->upsert($source, 'status_raw', 'valid_oper', [['key' => 'trim']], order: 5);
    }

    private function seedSteg(): void
    {
        $source = Source::query()->where('code', 'STEG')->firstOrFail();

        $this->upsert($source, 'reference', 'SOURCE_STRING', [
            ['key' => 'substring_after_nth_delimiter', 'config' => ['delimiter' => ',', 'n' => 2, 'length' => 9]],
        ], required: true, order: 0);

        $this->upsert($source, 'secondary_reference', 'VALID', [
            ['key' => 'trim'],
            ['key' => 'strip_prefix_chars', 'config' => ['chars' => ['B', 'b']]],
        ], order: 1);

        $this->upsert($source, 'amount', 'MONTANT', [
            ['key' => 'trim'],
            ['key' => 'fixed_width_millimes'],
        ], required: true, order: 2);

        $this->upsert($source, 'datetime', 'DATE', [
            ['key' => 'date_parse', 'config' => ['format' => 'd/m/Y H:i:s', 'output' => 'datetime']],
        ], required: true, order: 3);
    }

    /** @param array<int,array<string,mixed>> $transform */
    private function upsert(Source $source, string $targetField, string $sourceColumn, array $transform, bool $required = false, int $order = 0): void
    {
        SourceColumnMapping::query()->updateOrCreate(
            ['source_id' => $source->id, 'target_field' => $targetField],
            [
                'source_column' => $sourceColumn,
                'transform' => $transform,
                'is_required' => $required,
                'sort_order' => $order,
            ]
        );
    }
}
