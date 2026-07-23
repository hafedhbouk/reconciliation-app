<?php

use App\Jobs\DetectDuplicatesJob;
use App\Jobs\NotifyMatchingBatchCompleteJob;
use App\Jobs\ProcessImportJob;
use App\Jobs\RunMatchingRuleJob;
use App\Jobs\SweepUnmatchedJob;
use App\Models\Import;
use App\Models\MatchingResult;
use App\Models\MatchingRule;
use App\Models\Source;
use App\Models\SourceColumnMapping;
use App\Models\User;
use App\Notifications\ImportProcessedNotification;
use App\Notifications\MatchingActionCompletedNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

test('a completed import notifies the user who triggered it', function () {
    Storage::fake('local');
    Notification::fake();

    $user = User::factory()->create();
    $source = Source::factory()->create(['file_type' => 'csv']);
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
    SourceColumnMapping::query()->create([
        'source_id' => $source->id,
        'target_field' => 'date',
        'source_column' => 'DATE',
        'transform' => [['key' => 'date_parse', 'config' => ['format' => 'd/m/Y', 'output' => 'date']]],
        'is_required' => true,
    ]);

    $csv = "NUM_AUTO,MONTANT,DATE\n934516, 000000042000,01/01/2026\n";
    Storage::disk('local')->put('imports/notif.csv', $csv);

    $import = Import::query()->create([
        'source_id' => $source->id,
        'original_filename' => 'notif.csv',
        'stored_path' => 'imports/notif.csv',
        'file_hash' => hash('sha256', $csv),
        'mime_type' => 'text/csv',
        'size_bytes' => strlen($csv),
        'status' => 'pending',
        'imported_by' => $user->id,
    ]);

    app(ProcessImportJob::class, ['importId' => $import->id])->handle(
        app(App\Services\Import\Readers\ImportRowReaderFactory::class),
        app(App\Services\Import\MappingEngine::class),
        app(App\Services\Import\TransactionNormalizer::class),
    );

    Notification::assertSentTo($user, ImportProcessedNotification::class, function ($notification) use ($import, $user) {
        return $notification->toDatabase($user)['import_id'] === $import->id
            && $notification->toDatabase($user)['status'] === 'completed';
    });
    Notification::assertCount(1);
});

test('a header-validation failure also notifies the user, with no imported_by sending nothing', function () {
    Storage::fake('local');
    Notification::fake();

    $source = Source::factory()->create(['file_type' => 'csv']);
    SourceColumnMapping::query()->create([
        'source_id' => $source->id,
        'target_field' => 'reference',
        'source_column' => 'NUM_AUTO',
        'transform' => [['key' => 'trim']],
        'is_required' => true,
    ]);

    $csv = "SOME_OTHER_HEADER\nvalue\n";
    Storage::disk('local')->put('imports/no-user.csv', $csv);

    $import = Import::query()->create([
        'source_id' => $source->id,
        'original_filename' => 'no-user.csv',
        'stored_path' => 'imports/no-user.csv',
        'file_hash' => hash('sha256', $csv),
        'mime_type' => 'text/csv',
        'size_bytes' => strlen($csv),
        'status' => 'pending',
        'imported_by' => null,
    ]);

    app(ProcessImportJob::class, ['importId' => $import->id])->handle(
        app(App\Services\Import\Readers\ImportRowReaderFactory::class),
        app(App\Services\Import\MappingEngine::class),
        app(App\Services\Import\TransactionNormalizer::class),
    );

    expect($import->refresh()->status->value)->toBe('failed');
    Notification::assertNothingSent();
});

test('RunMatchingRuleJob notifies the given user with a rule-run summary', function () {
    Notification::fake();
    $user = User::factory()->create();
    $rule = MatchingRule::factory()->create(['name' => 'ALPHA <-> BNA']);

    app(RunMatchingRuleJob::class, [
        'matchingRuleId' => $rule->id,
        'batchReference' => 'batch-1',
        'notifyUserId' => $user->id,
    ])->handle(app(App\Services\Matching\RuleMatcher::class));

    Notification::assertSentTo($user, MatchingActionCompletedNotification::class, function ($notification) use ($user) {
        return str_contains($notification->toDatabase($user)['title'], 'ALPHA');
    });
});

test('RunMatchingRuleJob sends no notification when notifyUserId is null', function () {
    Notification::fake();
    $rule = MatchingRule::factory()->create();

    app(RunMatchingRuleJob::class, [
        'matchingRuleId' => $rule->id,
        'batchReference' => 'batch-1',
        'notifyUserId' => null,
    ])->handle(app(App\Services\Matching\RuleMatcher::class));

    Notification::assertNothingSent();
});

test('DetectDuplicatesJob notifies the given user with a scan summary', function () {
    Notification::fake();
    $user = User::factory()->create();

    app(DetectDuplicatesJob::class, ['sourceId' => null, 'notifyUserId' => $user->id])
        ->handle(app(App\Services\Matching\DuplicateDetector::class));

    Notification::assertSentTo($user, MatchingActionCompletedNotification::class);
});

test('SweepUnmatchedJob notifies the given user with a sweep summary', function () {
    Notification::fake();
    $user = User::factory()->create();

    app(SweepUnmatchedJob::class, ['sourceId' => null, 'notifyUserId' => $user->id])
        ->handle(app(App\Services\Matching\UnmatchedSweeper::class));

    Notification::assertSentTo($user, MatchingActionCompletedNotification::class);
});

test('NotifyMatchingBatchCompleteJob aggregates matching results and exceptions for the batch', function () {
    Notification::fake();
    $user = User::factory()->create();

    MatchingResult::factory()->count(2)->create(['batch_reference' => 'batch-xyz', 'status' => 'matched', 'matched_at' => now()]);
    MatchingResult::factory()->create(['batch_reference' => 'batch-xyz', 'status' => 'conflict', 'matched_at' => now()]);
    MatchingResult::factory()->create(['batch_reference' => 'other-batch', 'status' => 'matched', 'matched_at' => now()]);

    app(NotifyMatchingBatchCompleteJob::class, ['batchReference' => 'batch-xyz', 'notifyUserId' => $user->id])->handle();

    Notification::assertSentTo($user, MatchingActionCompletedNotification::class, function ($notification) use ($user) {
        $data = $notification->toDatabase($user);

        return collect($data['lines'])->contains(fn ($line) => str_contains($line, '2') && str_contains($line, 'matched'))
            && collect($data['lines'])->contains(fn ($line) => str_contains($line, '1') && str_contains($line, 'conflict'));
    });
});
