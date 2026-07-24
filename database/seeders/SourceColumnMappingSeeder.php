<?php

namespace Database\Seeders;

use App\Models\Source;
use App\Models\SourceColumnMapping;
use Illuminate\Database\Seeder;

/**
 * Seeds the column mappings for ALPHA/BNA/WEB/SMT from column names and
 * cross-source value overlaps verified directly against the real sample
 * files (storage/app/samples/, not committed). One correction worth noting:
 * for ALPHA, SMT and WEB, the field each source's own documentation would
 * call "the reference" is NOT the field that actually correlates
 * cross-source — NUM_AUTO (ALPHA) and recu_paie (SMT, WEB), once their
 * conditional leading b/B is stripped, are 6-digit codes verified to overlap
 * with BNA's N° autorisation. Those are mapped as the core `reference`
 * field; each source's own internal reference becomes an auxiliary
 * `secondary_reference` instead. A second correction, found only once Phase
 * 3's matching engine ran against real re-imported data: SMT/WEB's `date`
 * field must come from `date_paiement` (SMT: now "New Deposit date" — see
 * client correction below), not the more obviously-named
 * `DATE_FORMAT(`date_au`, '%d%m%Y')` column -- see seedSmt() for the
 * cross-source evidence.
 *
 * Client correction (2026-07): WEB is STEG's online payment portal, not the
 * generic gateway file originally assumed — its real columns turned out to
 * match the same shape as SMT's (fused "session,référence" column,
 * date_paiement, recu_paie), so seedWeb() was rebuilt on that same pattern.
 * SMT's own date column was also renamed to "New Deposit date" in its
 * current export. Both corrections supersede whatever the previous sample
 * files showed; source_column names should still be re-verified against a
 * real file via the mapping association screen (admin.sources.mappings.edit)
 * before relying on this in production, same as the STEG placeholder below.
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
        // export, not the generic gateway file this mapping originally
        // assumed. Its real header for the reference is a single fused
        // "session,référence" column (a session id and the reference
        // crammed into one field by the source system) -- the session part
        // is of no interest, so the reference is taken as the rightmost 9
        // digits regardless of where the comma actually falls. Mirrors the
        // same reference-field precedent as SMT below: recu_paie (once its
        // conditional b/B prefix is stripped) is the verified cross-source
        // matching key, so it stays the primary `reference`; the fused
        // column becomes an auxiliary `secondary_reference`.
        $this->upsert($source, 'reference', 'recu_paie', [
            ['key' => 'trim'],
            ['key' => 'strip_prefix_chars', 'config' => ['chars' => ['B', 'b']]],
        ], required: true, order: 0);

        $this->upsert($source, 'secondary_reference', 'session,référence', [
            ['key' => 'trim'],
            ['key' => 'substring_from_right', 'config' => ['length' => 9]],
        ], order: 1);

        $this->upsert($source, 'amount', 'Montant', [
            ['key' => 'trim'],
            ['key' => 'fixed_width_millimes'],
        ], required: true, order: 2);

        // Same DATE_FORMAT(...) vs date_paiement distinction as SMT below --
        // DATE_FORMAT() has no known reconciliation role and is left
        // unmapped; date_paiement is the real transaction date.
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

        $this->upsert($source, 'reference', 'recu_paie', [
            ['key' => 'trim'],
            ['key' => 'strip_prefix_chars', 'config' => ['chars' => ['B', 'b']]],
        ], required: true, order: 0);

        $this->upsert($source, 'secondary_reference', 'reference', [['key' => 'trim']], order: 1);

        $this->upsert($source, 'amount', 'Montant', [
            ['key' => 'trim'],
            ['key' => 'fixed_width_millimes'],
        ], required: true, order: 2);

        // Client correction (2026-07): the current SMT export's date column
        // is literally named "New Deposit date", replacing date_paiement.
        // recu_paie is kept as the `reference` field regardless -- it's the
        // verified cross-source matching key (see class docblock); without
        // it SMT rows could never be picked up by automatic matching, only
        // manual reconciliation. If "New Deposit date"'s actual format
        // turns out to differ from date_paiement's ("Y-m-d H:i:s"), the
        // per-row error will surface on the import's detail page -- fix the
        // format via Sources → Mappings, not by guessing here.
        $this->upsert($source, 'date', 'New Deposit date', [
            ['key' => 'date_parse', 'config' => ['format' => 'Y-m-d H:i:s', 'output' => 'date']],
        ], required: true, order: 3);

        $this->upsert($source, 'datetime', 'New Deposit date', [
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
