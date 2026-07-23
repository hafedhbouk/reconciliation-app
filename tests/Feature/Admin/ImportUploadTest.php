<?php

use App\Jobs\ProcessImportJob;
use App\Models\Import;
use App\Models\Source;
use App\Models\SourceColumnMapping;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

function seedRequiredCsvMapping(Source $source): void
{
    SourceColumnMapping::query()->create([
        'source_id' => $source->id,
        'target_field' => 'reference',
        'source_column' => 'NUM_AUTO',
        'transform' => [['key' => 'trim']],
        'is_required' => true,
    ]);

    SourceColumnMapping::query()->create([
        'source_id' => $source->id,
        'target_field' => 'amount',
        'source_column' => 'MONTANT',
        'transform' => [['key' => 'trim'], ['key' => 'fixed_width_millimes']],
        'is_required' => true,
    ]);
}

test('it dispatches ProcessImportJob when uploaded headers satisfy the required mapping', function () {
    Queue::fake();
    Storage::fake('local');
    actingAsAdmin();

    $source = Source::factory()->create(['file_type' => 'csv']);
    seedRequiredCsvMapping($source);

    $file = UploadedFile::fake()->createWithContent('alpha.csv', "NUM_AUTO,MONTANT\nb123456, 000000042000\n");

    $response = $this->post(route('admin.imports.store'), [
        'source_id' => $source->id,
        'file' => $file,
    ]);

    $import = Import::query()->where('source_id', $source->id)->sole();
    $response->assertRedirect(route('admin.imports.show', $import));
    Queue::assertPushed(ProcessImportJob::class, fn ($job) => $job->importId === $import->id);
});

test('it redirects to the mapping screen when required headers are missing from the upload', function () {
    Queue::fake();
    Storage::fake('local');
    actingAsAdmin();

    $source = Source::factory()->create(['file_type' => 'csv']);
    seedRequiredCsvMapping($source);

    $file = UploadedFile::fake()->createWithContent('alpha.csv', "SOME_OTHER_COLUMN\nvalue\n");

    $response = $this->post(route('admin.imports.store'), [
        'source_id' => $source->id,
        'file' => $file,
    ]);

    $import = Import::query()->where('source_id', $source->id)->sole();
    $response->assertRedirect(route('admin.sources.mappings.edit', ['source' => $source, 'import' => $import->id]));
    Queue::assertNotPushed(ProcessImportJob::class);
});

test('it warns before re-importing a file with an identical hash', function () {
    Queue::fake();
    Storage::fake('local');
    actingAsAdmin();

    $source = Source::factory()->create(['file_type' => 'csv']);
    seedRequiredCsvMapping($source);

    $content = "NUM_AUTO,MONTANT\nb123456, 000000042000\n";

    $this->post(route('admin.imports.store'), [
        'source_id' => $source->id,
        'file' => UploadedFile::fake()->createWithContent('alpha.csv', $content),
    ]);

    expect(Import::query()->where('source_id', $source->id)->count())->toBe(1);

    $response = $this->post(route('admin.imports.store'), [
        'source_id' => $source->id,
        'file' => UploadedFile::fake()->createWithContent('alpha-again.csv', $content),
    ]);

    $response->assertSessionHas('duplicate_warning');
    expect(Import::query()->where('source_id', $source->id)->count())->toBe(1);

    $response = $this->post(route('admin.imports.store'), [
        'source_id' => $source->id,
        'file' => UploadedFile::fake()->createWithContent('alpha-again.csv', $content),
        'confirmed_duplicate' => '1',
    ]);

    $response->assertSessionHasNoErrors();
    expect(Import::query()->where('source_id', $source->id)->count())->toBe(2);
});

test('the upload rejects a file whose extension does not match the source file type', function () {
    Storage::fake('local');
    actingAsAdmin();

    $source = Source::factory()->create(['file_type' => 'xlsx']);
    seedRequiredCsvMapping($source);

    $file = UploadedFile::fake()->createWithContent('alpha.csv', "NUM_AUTO,MONTANT\nb123456, 000000042000\n");

    $response = $this->post(route('admin.imports.store'), [
        'source_id' => $source->id,
        'file' => $file,
    ]);

    $response->assertSessionHasErrors('file');
});

test('plain user is forbidden from creating an import', function () {
    Storage::fake('local');
    actingAsPlainUser();

    $source = Source::factory()->create(['file_type' => 'csv']);
    $file = UploadedFile::fake()->createWithContent('alpha.csv', "A,B\n1,2\n");

    $this->post(route('admin.imports.store'), [
        'source_id' => $source->id,
        'file' => $file,
    ])->assertForbidden();
});
