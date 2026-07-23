<?php

use App\Exceptions\Import\MissingRequiredFieldException;
use App\Exceptions\Import\RowTransformException;
use App\Models\SourceColumnMapping;
use App\Services\Import\MappingEngine;
use App\Services\Import\TransformRegistry;
use Illuminate\Support\Collection;

function makeSourceColumnMapping(string $targetField, string $sourceColumn, array $transform = [], bool $required = false): SourceColumnMapping
{
    return new SourceColumnMapping([
        'target_field' => $targetField,
        'source_column' => $sourceColumn,
        'transform' => $transform,
        'is_required' => $required,
    ]);
}

test('it transforms a raw row into a keyed array using the mapping chain', function () {
    $engine = new MappingEngine(new TransformRegistry());

    $mappings = new Collection([
        makeSourceColumnMapping('reference', 'NUM_AUTO', [
            ['key' => 'trim'],
            ['key' => 'strip_prefix_chars', 'config' => ['chars' => ['B', 'b']]],
        ], required: true),
        makeSourceColumnMapping('amount', 'MONTANT_ENCAISS', [
            ['key' => 'trim'],
            ['key' => 'fixed_width_millimes'],
        ], required: true),
        makeSourceColumnMapping('canal', 'CANAL', [['key' => 'trim']]),
    ]);

    $result = $engine->transformRow([
        'NUM_AUTO' => 'b934516',
        'MONTANT_ENCAISS' => ' 000000042000',
        'CANAL' => 'WEB',
    ], $mappings);

    expect($result)->toBe([
        'reference' => '934516',
        'amount' => 42000,
        'canal' => 'WEB',
    ]);
});

test('it passes through a value untouched when no transform steps are configured', function () {
    $engine = new MappingEngine(new TransformRegistry());
    $mappings = new Collection([makeSourceColumnMapping('status_raw', 'valid_oper')]);

    $result = $engine->transformRow(['valid_oper' => '1'], $mappings);

    expect($result)->toBe(['status_raw' => '1']);
});

test('it sets an optional missing field to null instead of failing', function () {
    $engine = new MappingEngine(new TransformRegistry());
    $mappings = new Collection([makeSourceColumnMapping('canal', 'CANAL')]);

    $result = $engine->transformRow([], $mappings);

    expect($result)->toBe(['canal' => null]);
});

test('it throws MissingRequiredFieldException when a required column is absent', function () {
    $engine = new MappingEngine(new TransformRegistry());
    $mappings = new Collection([makeSourceColumnMapping('reference', 'REFERENCE', required: true)]);

    $engine->transformRow([], $mappings);
})->throws(MissingRequiredFieldException::class);

test('it wraps a primitive failure in RowTransformException naming the target field', function () {
    $engine = new MappingEngine(new TransformRegistry());
    $mappings = new Collection([
        makeSourceColumnMapping('date', 'DAT_ENC', [['key' => 'date_parse', 'config' => ['format' => 'd/m/Y']]], required: true),
    ]);

    try {
        $engine->transformRow(['DAT_ENC' => 'not-a-date'], $mappings);
        expect(false)->toBeTrue('Expected RowTransformException to be thrown');
    } catch (RowTransformException $e) {
        expect($e->targetField)->toBe('date');
        expect($e->getMessage())->toContain('date:');
    }
});

test('validateHeaders returns missing required source columns', function () {
    $engine = new MappingEngine(new TransformRegistry());
    $required = new Collection([
        makeSourceColumnMapping('reference', 'N° autorisation', required: true),
        makeSourceColumnMapping('amount', 'Montant (TND)', required: true),
    ]);

    $missing = $engine->validateHeaders(['N° autorisation', 'Date'], $required);

    expect($missing)->toBe(['Montant (TND)']);
});

test('validateHeaders returns an empty array when all required columns are present', function () {
    $engine = new MappingEngine(new TransformRegistry());
    $required = new Collection([makeSourceColumnMapping('reference', 'N° autorisation', required: true)]);

    $missing = $engine->validateHeaders(['N° autorisation', 'Date'], $required);

    expect($missing)->toBe([]);
});
