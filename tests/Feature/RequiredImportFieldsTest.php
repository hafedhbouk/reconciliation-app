<?php

use App\Exceptions\Import\MissingRequiredFieldException;
use App\Models\Source;
use App\Models\SourceColumnMapping;
use App\Services\Import\MappingEngine;
use Database\Seeders\BankSeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\SourceColumnMappingSeeder;
use Database\Seeders\SourceSeeder;

test('each source imports all required fields and strips only the authorization prefix', function (string $code, string $prefix) {
    $this->seed([CurrencySeeder::class, BankSeeder::class, SourceSeeder::class]);
    Source::factory()->create(['code' => 'STEG']);
    $this->seed(SourceColumnMappingSeeder::class);
    $source = Source::where('code', $code)->sole();
    $mappings = SourceColumnMapping::where('source_id', $source->id)->get();
    $raw = match ($code) {
        'ALPHA' => ['REFERENCE' => '001234567', 'MONTANT_ENCAISS' => '000000016000', 'DAT_ENC' => '01/02/2026', 'NUM_AUTO' => ' '.$prefix.'003512 '],
        'BNA' => ['N° autorisation' => '003512', 'Date' => '01/02/2026', 'Montant (TND)' => '16.000'],
        'SMT' => ['New Deposit date' => '2026.02.01 12:34:56', 'Montant' => '16.000'],
        default => ['reference' => '1234567', 'montant' => '000000016000', 'date_paiement' => '2026-02-01 12:34:56', 'recu_paie' => ' '.$prefix.'003512 '],
    };
    $engine = app(MappingEngine::class);
    expect($mappings->where('is_required', true)->pluck('source_column')->all())->toEqualCanonicalizing(array_keys($raw));
    expect($engine->validateHeaders(array_keys($raw), $mappings->where('is_required', true)))->toBe([]);
    $mapped = $engine->transformRow($raw, $mappings);
    expect($mapped['date'])->toBe('2026-02-01');
    expect($mapped['amount'])->toBe(16000);
    if ($code !== 'SMT') {
        expect($mapped[in_array($code, ['WEB', 'STEG']) ? 'secondary_reference' : 'num_autorisation'])->toBe('003512');
    }
    if (in_array($code, ['ALPHA', 'WEB', 'STEG'])) {
        expect($mapped['reference'])->toBe('001234567');
    }
    foreach (array_keys($raw) as $column) {
        $missing = $raw;
        unset($missing[$column]);
        expect($engine->validateHeaders(array_keys($missing), $mappings->where('is_required', true)))->toBe([$column]);
        expect(fn () => $engine->transformRow($missing, $mappings))->toThrow(MissingRequiredFieldException::class);
    }
})->with(['ALPHA', 'BNA', 'WEB', 'STEG', 'SMT'])->with(['b', 'B', '']);

test('updating legacy mappings removes obsolete references and SMT requirements', function () {
    $this->seed([CurrencySeeder::class, BankSeeder::class, SourceSeeder::class]);
    foreach ([['ALPHA', 'secondary_reference'], ['BNA', 'reference'], ['SMT', 'reference']] as [$code, $field]) {
        SourceColumnMapping::create([
            'source_id' => Source::where('code', $code)->sole()->id,
            'target_field' => $field,
            'source_column' => 'Obsolete authorization column',
            'transform' => [],
            'is_required' => true,
            'sort_order' => 0,
        ]);
    }
    $this->seed(SourceColumnMappingSeeder::class);
    expect(SourceColumnMapping::where('source_column', 'Obsolete authorization column')->count())->toBe(0);
    $smt = Source::where('code', 'SMT')->sole();
    expect(SourceColumnMapping::where('source_id', $smt->id)->pluck('source_column')->all())
        ->toEqualCanonicalizing(['New Deposit date', 'Montant']);
});
