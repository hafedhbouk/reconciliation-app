<?php

use App\Models\Source;
use App\Models\SourceColumnMapping;

test('admin can view the mapping association screen for a source', function () {
    actingAsAdmin();
    $source = Source::factory()->create();

    $this->get(route('admin.sources.mappings.edit', $source))->assertOk();
});

test('admin can save a mapping for a target field', function () {
    actingAsAdmin();
    $source = Source::factory()->create();

    $response = $this->put(route('admin.sources.mappings.update', $source), [
        'mappings' => [
            'reference' => [
                'source_column' => 'NUM_AUTO',
                'is_required' => '1',
                'transform_type' => 'strip_prefix_chars',
                'chars' => 'B,b',
            ],
        ],
    ]);

    $response->assertRedirect(route('admin.sources.edit', $source));

    $mapping = SourceColumnMapping::query()->where('source_id', $source->id)->where('target_field', 'reference')->sole();
    expect($mapping->source_column)->toBe('NUM_AUTO');
    expect($mapping->is_required)->toBeTrue();
    expect($mapping->transform)->toBe([
        ['key' => 'trim'],
        ['key' => 'strip_prefix_chars', 'config' => ['chars' => ['B', 'b']]],
    ]);
});

test('clearing a source_column removes the existing mapping', function () {
    actingAsAdmin();
    $source = Source::factory()->create();
    SourceColumnMapping::query()->create([
        'source_id' => $source->id,
        'target_field' => 'canal',
        'source_column' => 'CANAL',
        'transform' => [['key' => 'trim']],
        'is_required' => false,
    ]);

    $this->put(route('admin.sources.mappings.update', $source), [
        'mappings' => [
            'canal' => ['source_column' => ''],
        ],
    ]);

    expect(SourceColumnMapping::query()->where('source_id', $source->id)->where('target_field', 'canal')->exists())->toBeFalse();
});

test('saving a mapping redirects to the import when import_id is present', function () {
    actingAsAdmin();
    $source = Source::factory()->create();
    $import = App\Models\Import::factory()->create(['source_id' => $source->id]);

    $response = $this->put(route('admin.sources.mappings.update', $source), [
        'import_id' => $import->id,
        'mappings' => [
            'amount' => ['source_column' => 'MONTANT', 'transform_type' => 'fixed_width_millimes'],
        ],
    ]);

    $response->assertRedirect(route('admin.imports.show', $import));
});

test('plain user is forbidden from updating a source mapping', function () {
    actingAsPlainUser();
    $source = Source::factory()->create();

    $this->put(route('admin.sources.mappings.update', $source), ['mappings' => []])->assertForbidden();
});
