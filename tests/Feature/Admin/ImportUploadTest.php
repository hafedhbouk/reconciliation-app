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

test('process dispatches the job and stamps job_dispatched_at when never dispatched before', function () {
    Queue::fake();
    Storage::fake('local');
    $admin = actingAsAdmin();

    $source = Source::factory()->create(['file_type' => 'csv']);
    seedRequiredCsvMapping($source);

    $content = "NUM_AUTO,MONTANT\nb123456, 000000042000\n";
    Storage::disk('local')->put('imports/alpha.csv', $content);

    $import = Import::query()->create([
        'source_id' => $source->id,
        'original_filename' => 'alpha.csv',
        'stored_path' => 'imports/alpha.csv',
        'file_hash' => hash('sha256', $content),
        'mime_type' => 'text/csv',
        'size_bytes' => strlen($content),
        'status' => 'pending',
        'imported_by' => $admin->id,
    ]);

    expect($import->job_dispatched_at)->toBeNull();

    $response = $this->post(route('admin.imports.process', $import));

    $response->assertRedirect(route('admin.imports.show', $import));
    Queue::assertPushed(ProcessImportJob::class, fn ($job) => $job->importId === $import->id);
    expect($import->fresh()->job_dispatched_at)->not->toBeNull();
});

test('process refuses to dispatch a second job for an import that is already queued', function () {
    Queue::fake();
    Storage::fake('local');
    $admin = actingAsAdmin();

    $source = Source::factory()->create(['file_type' => 'csv']);
    seedRequiredCsvMapping($source);

    $content = "NUM_AUTO,MONTANT\nb123456, 000000042000\n";
    Storage::disk('local')->put('imports/alpha.csv', $content);

    // Simulates the store() happy path: a job was already dispatched and
    // the worker just hasn't picked it up yet, so status is still "pending".
    $import = Import::query()->create([
        'source_id' => $source->id,
        'original_filename' => 'alpha.csv',
        'stored_path' => 'imports/alpha.csv',
        'file_hash' => hash('sha256', $content),
        'mime_type' => 'text/csv',
        'size_bytes' => strlen($content),
        'status' => 'pending',
        'job_dispatched_at' => now(),
        'imported_by' => $admin->id,
    ]);

    $response = $this->post(route('admin.imports.process', $import));

    $response->assertRedirect(route('admin.imports.show', $import));
    Queue::assertNotPushed(ProcessImportJob::class);
});
