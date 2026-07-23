<?php

use App\Models\Import;
use App\Models\ImportRow;

test('admin can view the imports index page', function () {
    actingAsAdmin();

    $this->get(route('admin.imports.index'))->assertOk();
});

test('admin can fetch imports via the datatables ajax endpoint', function () {
    actingAsAdmin();
    Import::factory()->count(2)->create();

    $response = $this->getJson(route('admin.imports.data'));

    $response->assertOk();
    $response->assertJsonPath('recordsTotal', 2);
});

test('admin can view an import detail page with its rows', function () {
    actingAsAdmin();
    $import = Import::factory()->create();
    ImportRow::query()->create([
        'import_id' => $import->id,
        'row_number' => 1,
        'raw_data' => ['foo' => 'bar'],
        'status' => 'imported',
    ]);

    $response = $this->get(route('admin.imports.show', $import));

    $response->assertOk();
    $response->assertSee($import->original_filename);
});

test('the import detail page can be filtered by row status', function () {
    actingAsAdmin();
    $import = Import::factory()->create();
    ImportRow::query()->create(['import_id' => $import->id, 'row_number' => 1, 'raw_data' => [], 'status' => 'imported']);
    ImportRow::query()->create(['import_id' => $import->id, 'row_number' => 2, 'raw_data' => [], 'status' => 'error', 'error_message' => 'boom']);

    $response = $this->get(route('admin.imports.show', ['import' => $import, 'status' => 'error']));

    $response->assertOk();
    $response->assertSee('boom');
});

test('plain user is forbidden from viewing imports', function () {
    actingAsPlainUser();
    $import = Import::factory()->create();

    $this->get(route('admin.imports.index'))->assertForbidden();
    $this->get(route('admin.imports.show', $import))->assertForbidden();
});

test('guest is redirected to login when accessing imports', function () {
    $this->get(route('admin.imports.index'))->assertRedirect(route('login'));
});
