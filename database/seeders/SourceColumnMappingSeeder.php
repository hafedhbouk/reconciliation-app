<?php

namespace Database\Seeders;

use App\Models\Source;
use App\Models\SourceColumnMapping;
use Illuminate\Database\Seeder;

/**
 * Seeds the column mappings for ALPHA/BNA/WEB/SMT from column names and
 * cross-source value overlaps verified directly against real sample files
 * and test imports. One correction worth noting: for ALPHA and WEB, the
 * field each source's own documentation would call "the reference" is NOT
 * the field that actually correlates cross-source — NUM_AUTO (ALPHA) and
 * recu_paie (WEB), once their conditional leading b/B is stripped, are
 * 6-digit codes verified to overlap with BNA's N° autorisation. Those are
 * mapped as the core `reference` field; each source's own internal
 * reference becomes an auxiliary `secondary_reference` instead. SMT plays
 * the same role via "Identifiant de la réponse d'autorisation".
 *
 * Client correction (2026-07): WEB and SMT's actual file structures were
 * initially swapped — WEB is STEG's online payment portal (session,
 * reference, DATE_FORMAT(`date_au`, '%d%m%Y'), montant, date_paiement,
 * recu_paie, valid_oper — comma-delimited), while SMT is the payment-gateway
 * export (Order type, Acquéreur, ..., Identifiant de la réponse
 * d'autorisation, Numéro de référence, ... — semicolon-delimited, mangled
 * accents). A prior fix mistakenly gave WEB a "fused session,référence
 * column" workaround: that symptom was actually each source having the
 * other's delimiter configured (see SourceSeeder), which makes fgetcsv()
 * return the entire line as one field — indistinguishable from a genuinely
 * fused column in the mapping screen until the delimiter mismatch is
 * understood. Confirmed against real headers/rows from both files; still
 * worth re-verifying via the mapping association screen
 * (admin.sources.mappings.edit) against a full file before trusting this in
 * production, same as the STEG placeholder below.
 *
 * STEG has no sample file (client-confirmed). Its rows below are placeholders
 * built only from the client's written rules — source_column names are
 * best-guesses and MUST be revisited via the mapping association screen
 * the moment a real STEG file arrives.
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

        // REFERENCE is a fixed 9-digit code, but the xlsx sheet stores it as
        // a numeric cell -- Excel/PhpSpreadsheet silently drops leading
        // zeros on read, so it's padded back even when it starts with 0s.
        $this->upsert($source, 'secondary_reference', 'REFERENCE', [
            ['key' => 'trim'],
            ['key' => 'zero_pad', 'config' => ['length' => 9]],
        ], order: 1);

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

        // N° autorisation is a fixed 6-digit code, but the xlsx sheet stores
        // it as a numeric cell -- Excel/PhpSpreadsheet silently drops
        // leading zeros on read ("003512" -> "3512"), so it's padded back.
        $this->upsert($source, 'reference', 'N° autorisation', [
            ['key' => 'trim'],
            ['key' => 'zero_pad', 'config' => ['length' => 6]],
        ], required: true, order: 0);

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

        // Client correction (2026-07): WEB is STEG's online payment portal
        // export -- session, reference, DATE_FORMAT(`date_au`, '%d%m%Y'),
        // montant, date_paiement, recu_paie, valid_oper, comma-delimited.
        // "session" and "reference" are two genuinely separate columns, not
        // a fused one -- the CSV parses cleanly once the delimiter below is
        // correct (see SourceSeeder; this was previously misdiagnosed as a
        // fused column because the wrong delimiter made fgetcsv() return
        // the entire line as a single field, which happened to *look*
        // fused in the mapping screen). recu_paie (once its conditional
        // b/B prefix is stripped) stays the primary `reference` -- it's the
        // verified cross-source matching key (see class docblock); the
        // file's own `reference` column becomes an auxiliary
        // `secondary_reference`, no extraction needed.
        $this->upsert($source, 'reference', 'recu_paie', [
            ['key' => 'trim'],
            ['key' => 'strip_prefix_chars', 'config' => ['chars' => ['B', 'b']]],
        ], required: true, order: 0);

        $this->upsert($source, 'secondary_reference', 'reference', [['key' => 'trim']], order: 1);

        $this->upsert($source, 'amount', 'montant', [
            ['key' => 'trim'],
            ['key' => 'fixed_width_millimes'],
        ], required: true, order: 2);

        // DATE_FORMAT(`date_au`, '%d%m%Y') has no known reconciliation role
        // and is intentionally left unmapped; date_paiement is the real
        // transaction date.
        $this->upsert($source, 'date', 'date_paiement', [
            ['key' => 'date_parse', 'config' => ['format' => 'Y-m-d H:i:s', 'output' => 'date']],
        ], required: true, order: 3);

        $this->upsert($source, 'datetime', 'date_paiement', [
            ['key' => 'date_parse', 'config' => ['format' => 'Y-m-d H:i:s', 'output' => 'datetime']],
        ], order: 4);
    }

    private function seedSmt(): void
    {
        $source = Source::query()->where('code', 'SMT')->firstOrFail();

        // Client correction (2026-07): SMT is actually the payment-gateway
        // export (Order type, Acquéreur, ..., Montant, ..., Identifiant de
        // la réponse d'autorisation, Numéro de référence, ...), not the
        // session/recu_paie file previously assumed for it -- that
        // structure belongs to WEB (see seedWeb()). Semicolon-delimited.
        // The real file's bytes have literal '?' (0x3F) wherever an accented
        // character belongs -- verified neither UTF-8 nor CP1252 decoding
        // recovers the accents, so it isn't fixable on read; source_column
        // below MUST use this exact mangled form, not the grammatically
        // correct French text.
        $this->upsert($source, 'reference', "Identifiant de la r?ponse d'autorisation", [['key' => 'trim']], required: true, order: 0);

        $this->upsert($source, 'secondary_reference', 'Num?ro de r?f?rence', [['key' => 'trim']], order: 1);

        $this->upsert($source, 'amount', 'Montant', [
            ['key' => 'trim'],
            ['key' => 'decimal_string_to_millimes', 'config' => ['decimals' => 3]],
        ], required: true, order: 2);

        // The column literally named "New Deposit date" in the current
        // export (not "Date", which is the auth request time, nor "New
        // Payment date", which precedes the deposit).
        $this->upsert($source, 'date', 'New Deposit date', [
            ['key' => 'date_parse', 'config' => ['format' => 'Y.m.d H:i:s', 'output' => 'date']],
        ], required: true, order: 3);

        $this->upsert($source, 'datetime', 'New Deposit date', [
            ['key' => 'date_parse', 'config' => ['format' => 'Y.m.d H:i:s', 'output' => 'datetime']],
        ], order: 4);

        $this->upsert($source, 'currency_code', 'Devise', [['key' => 'trim']], order: 5);
        $this->upsert($source, 'status_raw', 'Etat du paiement', [['key' => 'trim']], order: 6);
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
