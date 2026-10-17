<?php

use App\Models\Import;
use App\Models\ImportRow;
use App\Models\NormalizedTransaction;
use App\Models\Source;
use App\Models\Transaction;

function legacyNormalizedRow(string $code, ?string $externalReference, array $payload, string $reference, ?string $secondary, array $shown): NormalizedTransaction
{
    $import = Import::factory()->create(['source_id' => Source::factory()->create(['code' => $code])->id]);
    $row = ImportRow::query()->create([
        'import_id' => $import->id, 'row_number' => 1, 'raw_data' => [], 'status' => 'imported', 'normalized_data' => $shown,
    ]);
    $transaction = Transaction::factory()->create([
        'source_id' => $import->source_id, 'import_id' => $import->id, 'import_row_id' => $row->id,
        'external_reference' => $externalReference, 'raw_payload' => $payload,
    ]);

    return NormalizedTransaction::factory()->create([
        'transaction_id' => $transaction->id,
        'normalized_reference' => $reference,
        'normalized_secondary_reference' => $secondary,
    ]);
}

test('existing BNA and ALPHA rows are aligned on the normalizer output', function () {
    // BNA as left by the first backfill: authorization duplicated as secondary on the import page.
    $bna = legacyNormalizedRow('BNA', null, ['num_autorisation' => '331533'], '2026-07-31|103000', '331533', [
        'normalized_reference' => '2026-07-31|103000', 'normalized_amount_millimes' => 103000, 'normalized_secondary_reference' => '331533',
    ]);
    $alpha = legacyNormalizedRow('ALPHA', '185113460', ['reference' => '185113460', 'num_autorisation' => '697464'], '185113460', null, [
        'normalized_reference' => '185113460', 'normalized_amount_millimes' => 989000,
    ]);

    (require database_path('migrations/2026_10_17_000001_backfill_normalized_references.php'))->up();

    expect($bna->fresh()->normalized_reference)->toBe('331533')
        ->and($bna->fresh()->normalized_secondary_reference)->toBeNull()
        ->and($bna->transaction->importRow->fresh()->normalized_data)->toEqualCanonicalizing([
            'normalized_reference' => '331533', 'normalized_amount_millimes' => 103000,
        ]);
    expect($alpha->fresh()->normalized_reference)->toBe('185113460')
        ->and($alpha->fresh()->normalized_secondary_reference)->toBe('697464')
        ->and($alpha->transaction->importRow->fresh()->normalized_data)->toEqualCanonicalizing([
            'normalized_reference' => '185113460', 'normalized_secondary_reference' => '697464', 'normalized_amount_millimes' => 989000,
        ]);
});
