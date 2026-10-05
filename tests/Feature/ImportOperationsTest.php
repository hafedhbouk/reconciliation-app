<?php

use App\Contracts\ImportRowReader;
use App\Jobs\ProcessImportJob;
use App\Models\Import;
use App\Models\ImportRow;
use App\Models\NormalizedTransaction;
use App\Models\Source;
use App\Models\SourceColumnMapping;
use App\Models\Transaction;
use App\Models\UnmatchedSnapshot;
use App\Services\Import\ImportMappingVersion;
use App\Services\Import\MappingEngine;
use App\Services\Import\Readers\ImportRowReaderFactory;
use App\Services\Import\TransactionNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

function operationsImport(): Import
{
    Storage::fake('local');
    $source = Source::factory()->create(['file_type' => 'csv']);
    foreach (['reference' => 'ref', 'amount' => 'amount', 'date' => 'date'] as $target => $column) {
        SourceColumnMapping::create(['source_id' => $source->id, 'target_field' => $target,
            'source_column' => $column, 'is_required' => true, 'transform' => []]);
    }
    $csv = "ref,amount,date\nfirst,1000,2026-05-01\n,2000,2026-05-01\nlast,3000,2026-05-01\n";
    Storage::put('imports/resume.csv', $csv);

    return Import::factory()->create(['source_id' => $source->id, 'stored_path' => 'imports/resume.csv',
        'file_hash' => hash('sha256', $csv), 'status' => 'pending']);
}

function processOperationsImport(Import $import, ?ImportRowReaderFactory $factory = null): void
{
    (new ProcessImportJob($import->id))->handle($factory ?? app(ImportRowReaderFactory::class), app(MappingEngine::class), app(TransactionNormalizer::class));
}

test('interrupted imports resume with their frozen mapping without reinserting successes or rejects', function () {
    $import = operationsImport();
    config(['imports.chunk_size' => 1]);
    $reader = new class implements ImportRowReader
    {
        public function headers(string $absolutePath, array $sourceConfig): array
        {
            return ['ref', 'amount', 'date'];
        }

        public function read(string $absolutePath, array $sourceConfig): Generator
        {
            yield 1 => ['ref' => 'first', 'amount' => '1000', 'date' => '2026-05-01'];
            yield 2 => ['ref' => '', 'amount' => '2000', 'date' => '2026-05-01'];
            throw new RuntimeException('interruption');
        }
    };
    $factory = Mockery::mock(ImportRowReaderFactory::class);
    $factory->shouldReceive('make')->andReturn($reader);
    expect(fn () => processOperationsImport($import, $factory))->toThrow(RuntimeException::class, 'interruption');
    $ids = ImportRow::orderBy('id')->pluck('id')->all();
    expect($import->fresh()->processed_rows)->toBe(2);
    (new ProcessImportJob($import->id))->failed(new RuntimeException('interruption'));
    SourceColumnMapping::where('source_id', $import->source_id)->where('target_field', 'reference')->update(['source_column' => 'different_column']);
    processOperationsImport($import);
    expect(ImportRow::orderBy('id')->limit(2)->pluck('id')->all())->toBe($ids);
    expect(ImportRow::count())->toBe(3);
    expect(NormalizedTransaction::count())->toBe(2);
    expect($import->fresh()->success_rows)->toBe(2);
    expect($import->fresh()->error_rows)->toBe(1);
    expect(app(ImportMappingVersion::class)->state($import->fresh()))->toBe('outdated');
    processOperationsImport($import);
    expect(ImportRow::count())->toBe(3);
});

test('resume refuses a modified source file', function () {
    $import = operationsImport();
    processOperationsImport($import);
    $import->update(['status' => 'failed']);
    Storage::append($import->stored_path, 'extra,1000,2026-05-01');
    expect(fn () => processOperationsImport($import))->toThrow(RuntimeException::class, 'Le fichier a changé');
    expect(ImportRow::count())->toBe(3);
});

test('a legacy partial import cannot silently adopt a new mapping on resume', function () {
    $import = operationsImport();
    ImportRow::create(['import_id' => $import->id, 'row_number' => 1, 'raw_data' => [], 'status' => 'error']);
    expect(fn () => processOperationsImport($import))->toThrow(RuntimeException::class, 'sans version de mapping');
    expect($import->fresh()->mapping_hash)->toBeNull();
});

test('difference pages paginate each side independently and migrate legacy cached arrays once', function () {
    actingAsAdmin();
    $a = Import::factory()->create();
    $b = Import::factory()->create();
    $rows = array_map(fn ($id) => ['id' => $id, 'reference' => 'ref-'.$id, 'source' => 'A', 'amount_millimes' => 1000, 'date' => '01/05/2026'], range(1, 121));
    $snapshot = UnmatchedSnapshot::create(['import_a_id' => $a->id, 'import_b_id' => $b->id,
        'status' => 'completed', 'completed_at' => now(), 'result_a' => $rows, 'result_b' => $rows]);
    $response = $this->get(route('admin.reconciliation.unmatched', ['import_a_id' => $a->id, 'import_b_id' => $b->id, 'page_a' => 2]));
    $response->assertOk()->assertViewHas('unmatchedA', fn ($page) => $page->total() === 121 && $page->count() === 50 && $page->items()[0]['id'] === 51)
        ->assertViewHas('unmatchedB', fn ($page) => $page->items()[0]['id'] === 1);
    expect($snapshot->fresh()->rows_persisted)->toBeTrue();
    expect(DB::table('unmatched_snapshots')->where('id', $snapshot->id)->value('result_a'))->toBeNull();
    expect(DB::table('unmatched_snapshot_rows')->count())->toBe(242);
    $this->get(route('admin.reconciliation.unmatched', ['import_a_id' => $a->id, 'import_b_id' => $b->id]))->assertOk();
    expect(DB::table('unmatched_snapshot_rows')->count())->toBe(242);
});

test('legacy Alpha BNA snapshots show authorization fields without rerunning comparison', function () {
    actingAsAdmin();
    $alpha = Source::factory()->create(['code' => 'ALPHA', 'name' => 'Alpha']);
    $bna = Source::factory()->create(['code' => 'BNA', 'name' => 'BNA']);
    $importA = Import::factory()->create(['source_id' => $alpha->id, 'status' => 'completed']);
    $importB = Import::factory()->create(['source_id' => $bna->id, 'status' => 'completed']);
    $transactionA = Transaction::factory()->create([
        'source_id' => $alpha->id,
        'import_id' => $importA->id,
        'external_reference' => '999999999',
        'raw_payload' => ['num_autorisation' => '001111'],
    ]);
    $rowA = NormalizedTransaction::factory()->create([
        'transaction_id' => $transactionA->id,
        'normalized_reference' => '999999999',
        'normalized_amount_millimes' => 75000,
        'normalized_date' => '2026-05-01',
    ]);
    $transactionB = Transaction::factory()->create([
        'source_id' => $bna->id,
        'import_id' => $importB->id,
        'external_reference' => null,
        'raw_payload' => ['num_autorisation' => '002222'],
    ]);
    $rowB = NormalizedTransaction::factory()->create([
        'transaction_id' => $transactionB->id,
        'normalized_reference' => '2026-05-15|109000',
        'normalized_amount_millimes' => 109000,
        'normalized_date' => '2026-05-15',
    ]);
    UnmatchedSnapshot::query()->create([
        'import_a_id' => $importA->id,
        'import_b_id' => $importB->id,
        'status' => 'completed',
        'completed_at' => now(),
        'result_a' => [[
            'id' => $rowA->id,
            'source' => 'ALPHA',
            'reference' => '999999999',
            'amount_millimes' => 75000,
            'date' => '01/05/2026',
        ]],
        'result_b' => [[
            'id' => $rowB->id,
            'source' => 'BNA',
            'reference' => '2026-05-15|109000',
            'amount_millimes' => 109000,
            'date' => '15/05/2026',
        ]],
    ]);

    $this->get(route('admin.reconciliation.unmatched', [
        'import_a_id' => $importA->id,
        'import_b_id' => $importB->id,
    ]))
        ->assertOk()
        ->assertSee('NUM_AUTO')
        ->assertSee('DAT_ENC')
        ->assertSee('MONTANT_ENCAISS')
        ->assertSee('N° autorisation')
        ->assertSee('001111')
        ->assertSee('002222')
        ->assertDontSee('999999999')
        ->assertDontSee('2026-05-15|109000');
});

test('legacy Alpha WEB snapshots show authorization and receipt fields without rerunning comparison', function () {
    actingAsAdmin();
    $alpha = Source::factory()->create(['code' => 'ALPHA', 'name' => 'Alpha']);
    $web = Source::factory()->create(['code' => 'WEB', 'name' => 'WEB / STEG']);
    $importA = Import::factory()->create(['source_id' => $alpha->id, 'status' => 'completed']);
    $importB = Import::factory()->create(['source_id' => $web->id, 'status' => 'completed']);
    $alphaReference = '123456789';
    $webReference = '987654321';
    $authorization = '001111';
    $transactionA = Transaction::factory()->create([
        'source_id' => $alpha->id,
        'import_id' => $importA->id,
        'external_reference' => $alphaReference,
        'raw_payload' => ['reference' => $alphaReference, 'num_autorisation' => $authorization],
    ]);
    $rowA = NormalizedTransaction::factory()->create([
        'transaction_id' => $transactionA->id,
        'normalized_reference' => $alphaReference,
        'normalized_amount_millimes' => 75000,
        'normalized_date' => '2026-05-01',
    ]);
    $transactionB = Transaction::factory()->create([
        'source_id' => $web->id,
        'import_id' => $importB->id,
        'external_reference' => $webReference,
        'raw_payload' => ['reference' => $webReference, 'secondary_reference' => $authorization],
    ]);
    $rowB = NormalizedTransaction::factory()->create([
        'transaction_id' => $transactionB->id,
        'normalized_reference' => $webReference,
        'normalized_amount_millimes' => 75000,
        'normalized_date' => '2026-05-01',
    ]);
    UnmatchedSnapshot::query()->create([
        'import_a_id' => $importA->id,
        'import_b_id' => $importB->id,
        'status' => 'completed',
        'completed_at' => now(),
        'result_a' => [[
            'id' => $rowA->id,
            'source' => 'ALPHA',
            'reference' => $alphaReference,
            'amount_millimes' => 75000,
            'date' => '01/05/2026',
        ]],
        'result_b' => [[
            'id' => $rowB->id,
            'source' => 'WEB',
            'reference' => $webReference,
            'amount_millimes' => 75000,
            'date' => '01/05/2026',
        ]],
    ]);

    $this->get(route('admin.reconciliation.unmatched', [
        'import_a_id' => $importA->id,
        'import_b_id' => $importB->id,
    ]))
        ->assertOk()
        ->assertSee('REFERENCE')
        ->assertSee('NUM_AUTO')
        ->assertSee('DAT_ENC')
        ->assertSee('MONTANT_ENCAISS')
        ->assertSee('reference')
        ->assertSee('recu_paie')
        ->assertSee('date_paiement')
        ->assertSee('montant')
        ->assertSee($alphaReference)
        ->assertSee($webReference)
        ->assertSee($authorization);
});

test('legacy BNA WEB snapshots show authorization and receipt fields without rerunning comparison', function () {
    actingAsAdmin();
    $bna = Source::factory()->create(['code' => 'BNA', 'name' => 'BNA']);
    $web = Source::factory()->create(['code' => 'WEB', 'name' => 'WEB / STEG']);
    $importA = Import::factory()->create(['source_id' => $bna->id, 'status' => 'completed']);
    $importB = Import::factory()->create(['source_id' => $web->id, 'status' => 'completed']);
    $authorization = '004321';
    $bnaReference = '2026-05-15|109000';
    $webReference = '987654321';
    $transactionA = Transaction::factory()->create([
        'source_id' => $bna->id,
        'import_id' => $importA->id,
        'external_reference' => null,
        'raw_payload' => ['num_autorisation' => $authorization],
    ]);
    $rowA = NormalizedTransaction::factory()->create([
        'transaction_id' => $transactionA->id,
        'normalized_reference' => $bnaReference,
        'normalized_amount_millimes' => 109000,
        'normalized_date' => '2026-05-15',
    ]);
    $transactionB = Transaction::factory()->create([
        'source_id' => $web->id,
        'import_id' => $importB->id,
        'external_reference' => $webReference,
        'raw_payload' => ['reference' => $webReference, 'secondary_reference' => $authorization],
    ]);
    $rowB = NormalizedTransaction::factory()->create([
        'transaction_id' => $transactionB->id,
        'normalized_reference' => $webReference,
        'normalized_amount_millimes' => 109000,
        'normalized_date' => '2026-05-15',
    ]);
    UnmatchedSnapshot::query()->create([
        'import_a_id' => $importA->id,
        'import_b_id' => $importB->id,
        'status' => 'completed',
        'completed_at' => now(),
        'result_a' => [[
            'id' => $rowA->id,
            'source' => 'BNA',
            'reference' => $bnaReference,
            'amount_millimes' => 109000,
            'date' => '15/05/2026',
        ]],
        'result_b' => [[
            'id' => $rowB->id,
            'source' => 'WEB',
            'reference' => $webReference,
            'amount_millimes' => 109000,
            'date' => '15/05/2026',
        ]],
    ]);

    $this->get(route('admin.reconciliation.unmatched', [
        'import_a_id' => $importA->id,
        'import_b_id' => $importB->id,
    ]))
        ->assertOk()
        ->assertSee('N° autorisation')
        ->assertSee('Date')
        ->assertSee('Montant')
        ->assertSee('recu_paie')
        ->assertSee('date_paiement')
        ->assertSee('montant')
        ->assertSee($authorization)
        ->assertDontSee($bnaReference)
        ->assertDontSee($webReference);
});

test('the import page displays mapping warnings and balanced accepted amounts', function () {
    actingAsAdmin();
    $import = operationsImport();
    processOperationsImport($import);
    $this->get(route('admin.imports.show', $import))->assertOk()->assertSee('4000')->assertViewHas('mappingState', 'current');
    SourceColumnMapping::where('source_id', $import->source_id)->where('target_field', 'reference')->update(['source_column' => 'changed']);
    $this->get(route('admin.imports.show', $import))->assertOk()->assertViewHas('mappingState', 'outdated');
});

test('a header failure before any row may adopt the corrected mapping when relaunched', function () {
    actingAsAdmin();
    Queue::fake();
    $import = operationsImport();
    $mapping = SourceColumnMapping::where('source_id', $import->source_id)->where('target_field', 'reference')->sole();
    $mapping->update(['source_column' => 'wrong']);
    processOperationsImport($import);
    expect($import->fresh()->status->value)->toBe('failed');
    expect(ImportRow::count())->toBe(0);
    $mapping->update(['source_column' => 'ref']);
    $this->post(route('admin.imports.process', $import))->assertRedirect();
    processOperationsImport($import);
    expect($import->fresh()->success_rows)->toBe(2);
    expect(app(ImportMappingVersion::class)->state($import->fresh()))->toBe('current');
});
