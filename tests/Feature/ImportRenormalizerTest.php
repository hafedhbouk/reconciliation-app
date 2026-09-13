<?php

use App\Exceptions\Import\MissingRequiredFieldException;
use App\Models\Import;
use App\Models\ImportRow;
use App\Models\NormalizedTransaction;
use App\Models\Source;
use App\Models\Transaction;
use App\Services\Import\ImportRenormalizer;
use Database\Seeders\BankSeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\SourceColumnMappingSeeder;
use Database\Seeders\SourceSeeder;

beforeEach(function () {
    $this->seed([CurrencySeeder::class, BankSeeder::class, SourceSeeder::class, SourceColumnMappingSeeder::class]);
    $this->import = Import::factory()->create(['source_id' => Source::where('code', 'ALPHA')->sole()->id]);
    $this->row = ImportRow::create([
        'import_id' => $this->import->id, 'row_number' => 2, 'status' => 'imported',
        'raw_data' => ['REFERENCE' => '001234567', 'NUM_AUTO' => 'b3512', 'DAT_ENC' => '01/02/2026', 'MONTANT_ENCAISS' => '000000016000'],
        'transformed_data' => ['reference' => 'legacy'],
    ]);
    $this->transaction = Transaction::factory()->create([
        'import_id' => $this->import->id, 'import_row_id' => $this->row->id, 'source_id' => $this->import->source_id,
        'raw_payload' => ['reference' => 'legacy'],
    ]);
    $this->normalized = NormalizedTransaction::factory()->create([
        'transaction_id' => $this->transaction->id, 'normalized_reference' => 'legacy', 'matching_status' => 'conflict',
    ]);
    $this->path = storage_path('app/test-renormalize-'.bin2hex(random_bytes(6)).'.jsonl.gz');
});

afterEach(function () {
    if (is_file($this->path)) {
        unlink($this->path);
    }
});

test('renormalization updates original rows in place and preserves matching status', function () {
    $service = app(ImportRenormalizer::class);
    $service->prepare([$this->import->id], $this->path);
    expect($this->transaction->fresh()->raw_payload)->toBe(['reference' => 'legacy']);
    expect($service->apply($this->path))->toBe(1);
    expect(Transaction::count())->toBe(1);
    expect(NormalizedTransaction::count())->toBe(1);
    expect($this->transaction->fresh()->raw_payload['num_autorisation'])->toBe('003512');
    expect($this->normalized->fresh()->normalized_reference)->toBe('001234567');
    expect($this->normalized->fresh()->normalized_amount_millimes)->toBe(16000);
    expect($this->normalized->fresh()->normalized_date->format('Y-m-d'))->toBe('2026-02-01');
    expect($this->normalized->fresh()->matching_status->value)->toBe('conflict');
    expect($this->row->fresh()->transformed_data['num_autorisation'])->toBe('003512');
});

test('concurrent changes abort the entire operation', function () {
    $service = app(ImportRenormalizer::class);
    $service->prepare([$this->import->id], $this->path);
    $this->normalized->update(['normalized_reference' => 'changed-after-backup']);
    expect(fn () => $service->apply($this->path))->toThrow(RuntimeException::class);
    expect($this->transaction->fresh()->raw_payload)->toBe(['reference' => 'legacy']);
    expect($this->normalized->fresh()->normalized_reference)->toBe('changed-after-backup');
});

test('invalid source rows cannot produce an applicable plan', function () {
    $this->row->update(['raw_data' => ['REFERENCE' => '001234567']]);
    $service = app(ImportRenormalizer::class);
    expect(fn () => $service->prepare([$this->import->id], $this->path))
        ->toThrow(MissingRequiredFieldException::class);
    expect(fn () => $service->apply($this->path))->toThrow(RuntimeException::class);
    expect($this->transaction->fresh()->raw_payload)->toBe(['reference' => 'legacy']);
});
