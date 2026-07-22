<?php

use App\Models\Source;

test('admin can list sources', function () {
    actingAsAdmin();
    Source::factory()->count(2)->create();

    $this->get(route('admin.sources.index'))->assertOk();
});

test('admin can create a source', function () {
    actingAsAdmin();

    $response = $this->post(route('admin.sources.store'), [
        'code' => 'NEWSRC',
        'name' => 'New Source',
        'file_type' => 'csv',
        'is_active' => 1,
    ]);

    $response->assertRedirect(route('admin.sources.index'));
    $this->assertDatabaseHas('sources', ['code' => 'NEWSRC']);
});

test('admin can soft delete a source', function () {
    actingAsAdmin();
    $source = Source::factory()->create();

    $this->delete(route('admin.sources.destroy', $source))->assertRedirect(route('admin.sources.index'));

    $this->assertSoftDeleted('sources', ['id' => $source->id]);
});
