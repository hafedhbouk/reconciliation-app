<?php

use App\Enums\ExceptionStatus;
use App\Enums\ExceptionType;
use App\Models\ExceptionAttachment;
use App\Models\ExceptionRecord;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('admin can list exceptions', function () {
    actingAsAdmin();
    ExceptionRecord::factory()->count(2)->create();

    $this->get(route('admin.exceptions.index'))->assertOk();
});

test('admin can view an exception detail page', function () {
    actingAsAdmin();
    $exception = ExceptionRecord::factory()->create();

    $this->get(route('admin.exceptions.show', $exception))->assertOk();
});

test('resolving an exception sets resolved_by and resolved_at', function () {
    $admin = actingAsAdmin();
    $exception = ExceptionRecord::factory()->create(['status' => ExceptionStatus::Open->value]);

    $response = $this->put(route('admin.exceptions.update', $exception), [
        'status' => ExceptionStatus::Resolved->value,
        'resolution_comment' => 'Faux positif confirmé.',
    ]);

    $response->assertRedirect(route('admin.exceptions.show', $exception));

    $exception->refresh();
    expect($exception->status)->toBe(ExceptionStatus::Resolved);
    expect($exception->resolved_by)->toBe($admin->id);
    expect($exception->resolved_at)->not->toBeNull();
    expect($exception->resolution_comment)->toBe('Faux positif confirmé.');
});

test('reclassifying the type is a plain field update with no special handling', function () {
    actingAsAdmin();
    $exception = ExceptionRecord::factory()->create(['type' => ExceptionType::Unmatched->value]);

    $this->put(route('admin.exceptions.update', $exception), [
        'type' => ExceptionType::Orphan->value,
    ]);

    expect($exception->fresh()->type)->toBe(ExceptionType::Orphan);
});

test('admin can upload, download and delete an attachment', function () {
    Storage::fake('local');
    actingAsAdmin();
    $exception = ExceptionRecord::factory()->create();

    $file = UploadedFile::fake()->create('preuve.pdf', 100, 'application/pdf');
    $storeResponse = $this->post(route('admin.exceptions.attachments.store', $exception), ['file' => $file]);
    $storeResponse->assertRedirect(route('admin.exceptions.show', $exception));

    $attachment = ExceptionAttachment::query()->where('exception_id', $exception->id)->sole();
    expect($attachment->original_name)->toBe('preuve.pdf');
    Storage::disk('local')->assertExists($attachment->path);

    $this->get(route('admin.exceptions.attachments.download', [$exception, $attachment]))->assertOk();

    $destroyResponse = $this->delete(route('admin.exceptions.attachments.destroy', [$exception, $attachment]));
    $destroyResponse->assertRedirect(route('admin.exceptions.show', $exception));
    $this->assertSoftDeleted('exception_attachments', ['id' => $attachment->id]);
});

test('an executable file is rejected as an attachment', function () {
    Storage::fake('local');
    actingAsAdmin();
    $exception = ExceptionRecord::factory()->create();

    $file = UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream');
    $response = $this->post(route('admin.exceptions.attachments.store', $exception), ['file' => $file]);

    $response->assertSessionHasErrors('file');
    expect(ExceptionAttachment::query()->count())->toBe(0);
});

test('plain user is forbidden from updating an exception or its attachments', function () {
    actingAsPlainUser();
    $exception = ExceptionRecord::factory()->create();

    $this->put(route('admin.exceptions.update', $exception), ['status' => ExceptionStatus::Resolved->value])->assertForbidden();
    $this->post(route('admin.exceptions.attachments.store', $exception), ['file' => UploadedFile::fake()->create('x.pdf', 10)])->assertForbidden();
});
