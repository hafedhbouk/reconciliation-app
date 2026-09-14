<?php

use App\Jobs\GenerateMatchingExportJob;
use App\Models\MatchingExport;
use App\Models\MatchingResult;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

test('a background csv export contains only results matching its filters', function () {
    Storage::fake('local');
    $result = MatchingResult::factory()->create(['batch_reference' => 'included-batch']);
    MatchingResult::factory()->create(['batch_reference' => 'excluded-batch']);
    $export = MatchingExport::create([
        'user_id' => User::factory()->create()->id,
        'format' => 'csv',
        'status' => 'pending',
        'download_token' => (string) Str::uuid(),
        'filters' => [
            'matching_rule_id' => $result->matching_rule_id,
            'batch_reference' => 'included-batch',
            'status' => 'matched',
            'matched_at_from' => now()->toDateString(),
            'matched_at_to' => now()->toDateString(),
        ],
    ]);

    (new GenerateMatchingExportJob($export))->handle();

    $export->refresh();
    expect($export->isCompleted())->toBeTrue()
        ->and($export->isFailed())->toBeFalse()
        ->and($export->completed_at)->not->toBeNull();
    Storage::disk('local')->assertExists($export->file_path);
    $lines = preg_split('/\r?\n/', trim(Storage::disk('local')->get($export->file_path)));
    expect($lines)->toHaveCount(2)
        ->and($lines[1])->toContain($result->matchingRule->name)
        ->and($export->user->id)->toBe($export->user_id);
});

test('a failed background export records its error and remains unavailable', function () {
    Excel::shouldReceive('store')->once()->andThrow(new RuntimeException('Disk unavailable'));
    $export = MatchingExport::create([
        'user_id' => User::factory()->create()->id,
        'format' => 'xlsx',
        'status' => 'pending',
        'download_token' => (string) Str::uuid(),
    ]);
    $job = new GenerateMatchingExportJob($export);

    expect(fn () => $job->handle())->toThrow(RuntimeException::class, 'Disk unavailable');
    expect($export->refresh()->isFailed())->toBeTrue()
        ->and($export->isCompleted())->toBeFalse()
        ->and($export->error_message)->toBe('Disk unavailable')
        ->and($export->file_path)->toBeNull();

    $job->failed(new RuntimeException('Worker stopped'));
    expect($export->refresh()->error_message)->toBe('Worker stopped');
});
