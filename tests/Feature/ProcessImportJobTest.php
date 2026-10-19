<?php

use App\Jobs\ProcessImportJob;
use App\Models\Import;
use App\Models\ImportRow;
use App\Models\MatchingRule;
use App\Models\NormalizedTransaction;
use App\Models\Source;
use App\Models\SourceColumnMapping;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Import\MappingEngine;
use App\Services\Import\Readers\ImportRowReaderFactory;
use App\Services\Import\TransactionNormalizer;
use App\Services\Matching\RuleMatcher;
use Illuminate\Support\Facades\Storage;

function seedAlphaLikeMapping(Source $source): void
{
    SourceColumnMapping::query()->create([
        'source_id' => $source->id,
        'target_field' => 'reference',
        'source_column' => 'NUM_AUTO',
        'transform' => [
            ['key' => 'trim'],
            ['key' => 'strip_prefix_chars', 'config' => ['chars' => ['B', 'b']]],
        ],
        'is_required' => true,
    ]);

    SourceColumnMapping::query()->create([
        'source_id' => $source->id,
        'target_field' => 'amount',
        'source_column' => 'MONTANT_ENCAISS',
        'transform' => [
            ['key' => 'trim'],
            ['key' => 'fixed_width_millimes'],
        ],
        'is_required' => true,
    ]);

    SourceColumnMapping::query()->create([
        'source_id' => $source->id,
        'target_field' => 'date',
        'source_column' => 'DAT_ENC',
        'transform' => [['key' => 'date_parse', 'config' => ['format' => 'd/m/Y', 'output' => 'date']]],
        'is_required' => true,
    ]);
}

test('it processes a small CSV file end to end with per-row error isolation', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $source = Source::factory()->create(['file_type' => 'csv']);
    seedAlphaLikeMapping($source);

    $csv = "REFERENCE,MONTANT_ENCAISS,DAT_ENC,NUM_AUTO\n"
        ."203415400, 000000042000,01/01/2026,b934516\n" // happy path
        ."298723640, 000000098000,31-13-2026,b077025\n" // bad date -> per-row error
        ."334282420, 000000045000,01/01/2026,\n";        // missing required reference -> per-row error

    Storage::disk('local')->put('imports/test.csv', $csv);

    $import = Import::query()->create([
        'source_id' => $source->id,
        'original_filename' => 'test.csv',
        'stored_path' => 'imports/test.csv',
        'file_hash' => hash('sha256', $csv),
        'mime_type' => 'text/csv',
        'size_bytes' => strlen($csv),
        'status' => 'pending',
        'imported_by' => $user->id,
    ]);

    app(ProcessImportJob::class, ['importId' => $import->id])->handle(
        app(ImportRowReaderFactory::class),
        app(MappingEngine::class),
        app(TransactionNormalizer::class),
    );

    $import->refresh();

    expect($import->status->value)->toBe('partially_completed');
    expect($import->processed_rows)->toBe(3);
    expect($import->success_rows)->toBe(1);
    expect($import->error_rows)->toBe(2);

    expect(ImportRow::query()->where('import_id', $import->id)->where('status', 'error')->count())->toBe(2);
    expect(ImportRow::query()->where('import_id', $import->id)->where('status', 'imported')->count())->toBe(1);

    $errorRows = ImportRow::query()->where('import_id', $import->id)->where('status', 'error')->get();
    expect($errorRows->pluck('error_message')->filter()->count())->toBe(2);

    $transaction = Transaction::query()->where('import_id', $import->id)->sole();
    expect($transaction->external_reference)->toBe('934516');
    expect($transaction->amount_millimes)->toBe(42000);
    expect($transaction->transaction_date->format('Y-m-d'))->toBe('2026-01-01');
    expect($transaction->created_by)->toBe($user->id);

    $normalized = NormalizedTransaction::query()->where('transaction_id', $transaction->id)->sole();
    expect($normalized->normalized_reference)->toBe('934516');
    expect($normalized->normalized_amount_millimes)->toBe(42000);
    expect($normalized->matching_status->value)->toBe('unmatched');
});

test('WEB imports persist the transformed receipt in both normalized snapshots', function () {
    Storage::fake('local');
    $normalizedReceipt = '748363';
    $staleRawReceipt = '999999';
    $user = User::factory()->create();
    $source = Source::factory()->create(['code' => 'WEB', 'file_type' => 'csv']);
    foreach ([
        ['reference', 'reference', [['key' => 'trim'], ['key' => 'zero_pad', 'config' => ['length' => 9]]]],
        ['secondary_reference', 'recu_paie', [
            ['key' => 'trim'],
            ['key' => 'strip_prefix_chars', 'config' => ['chars' => ['B', 'b']]],
            ['key' => 'zero_pad', 'config' => ['length' => 6]],
        ]],
        ['amount', 'montant', [['key' => 'trim'], ['key' => 'fixed_width_millimes']]],
        ['date', 'date_paiement', [['key' => 'date_parse', 'config' => ['format' => 'Y-m-d H:i:s', 'output' => 'date']]]],
    ] as [$target, $column, $transforms]) {
        SourceColumnMapping::query()->create([
            'source_id' => $source->id,
            'target_field' => $target,
            'source_column' => $column,
            'transform' => $transforms,
            'is_required' => true,
        ]);
    }

    $csv = "reference,recu_paie,montant,date_paiement\n219243610,b{$normalizedReceipt},000000747000,2026-05-01 00:00:14\n";
    Storage::disk('local')->put('imports/web.csv', $csv);
    $import = Import::query()->create([
        'source_id' => $source->id,
        'original_filename' => 'web.csv',
        'stored_path' => 'imports/web.csv',
        'file_hash' => hash('sha256', $csv),
        'mime_type' => 'text/csv',
        'size_bytes' => strlen($csv),
        'status' => 'pending',
        'imported_by' => $user->id,
    ]);

    app(ProcessImportJob::class, ['importId' => $import->id])->handle(
        app(ImportRowReaderFactory::class),
        app(MappingEngine::class),
        app(TransactionNormalizer::class),
    );

    $importRow = ImportRow::query()->where('import_id', $import->id)->sole();
    $normalized = NormalizedTransaction::query()->sole();
    expect($importRow->normalized_data['normalized_secondary_reference'])->toBe($normalizedReceipt)
        ->and($normalized->normalized_secondary_reference)->toBe($normalizedReceipt);

    $normalized->transaction->update(['raw_payload' => ['reference' => '219243610', 'secondary_reference' => $staleRawReceipt]]);
    $bna = Source::factory()->create(['code' => 'BNA']);
    $bnaTransaction = Transaction::factory()->create([
        'source_id' => $bna->id,
        'external_reference' => null,
        'amount_millimes' => 747000,
        'transaction_date' => '2026-05-01',
        'raw_payload' => ['num_autorisation' => $normalizedReceipt],
    ]);
    $bnaNormalized = NormalizedTransaction::factory()->create([
        'transaction_id' => $bnaTransaction->id,
        // BNA's normalized_reference is its N° autorisation.
        'normalized_reference' => $normalizedReceipt,
        'normalized_amount_millimes' => 747000,
        'normalized_date' => '2026-05-01',
    ]);
    $rule = MatchingRule::factory()->create([
        'source_a_id' => $source->id,
        'source_b_id' => $bna->id,
        'criteria' => [
            'tolerance_amount_millimes' => 0,
            'tolerance_days' => 0,
            'excluded_status_raw' => ['a' => [], 'b' => []],
            'primary_key' => ['a' => 'secondary_reference', 'b' => 'num_autorisation'],
            'verify_fields' => ['amount', 'date'],
        ],
    ]);

    $summary = app(RuleMatcher::class)->match($rule, 'normalized-receipt-test');

    expect($summary->matched)->toBe(1)
        ->and($normalized->fresh()->matching_status->value)->toBe('matched')
        ->and($bnaNormalized->fresh()->matching_status->value)->toBe('matched');
});

test('it marks the import completed when every row succeeds', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $source = Source::factory()->create(['file_type' => 'csv']);
    seedAlphaLikeMapping($source);

    $csv = "REFERENCE,MONTANT_ENCAISS,DAT_ENC,NUM_AUTO\n"
        ."203415400, 000000042000,01/01/2026,b934516\n"
        ."298723640, 000000098000,02/01/2026,b077025\n";

    Storage::disk('local')->put('imports/ok.csv', $csv);

    $import = Import::query()->create([
        'source_id' => $source->id,
        'original_filename' => 'ok.csv',
        'stored_path' => 'imports/ok.csv',
        'file_hash' => hash('sha256', $csv),
        'mime_type' => 'text/csv',
        'size_bytes' => strlen($csv),
        'status' => 'pending',
        'imported_by' => $user->id,
    ]);

    app(ProcessImportJob::class, ['importId' => $import->id])->handle(
        app(ImportRowReaderFactory::class),
        app(MappingEngine::class),
        app(TransactionNormalizer::class),
    );

    expect($import->refresh()->status->value)->toBe('completed');
    expect($import->error_rows)->toBe(0);
    expect($import->success_rows)->toBe(2);
});

test('it fails the import outright when required headers are missing', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $source = Source::factory()->create(['file_type' => 'csv']);
    seedAlphaLikeMapping($source);

    $csv = "SOME_OTHER_HEADER,FOO\nvalue1,value2\n";
    Storage::disk('local')->put('imports/bad-headers.csv', $csv);

    $import = Import::query()->create([
        'source_id' => $source->id,
        'original_filename' => 'bad-headers.csv',
        'stored_path' => 'imports/bad-headers.csv',
        'file_hash' => hash('sha256', $csv),
        'mime_type' => 'text/csv',
        'size_bytes' => strlen($csv),
        'status' => 'pending',
        'imported_by' => $user->id,
    ]);

    app(ProcessImportJob::class, ['importId' => $import->id])->handle(
        app(ImportRowReaderFactory::class),
        app(MappingEngine::class),
        app(TransactionNormalizer::class),
    );

    expect($import->refresh()->status->value)->toBe('failed');
    expect($import->error_summary)->not->toBeNull();
});
