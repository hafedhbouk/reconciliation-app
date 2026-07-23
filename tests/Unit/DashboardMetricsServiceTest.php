<?php

use App\Enums\ExceptionStatus;
use App\Enums\ExceptionType;
use App\Enums\ImportStatus;
use App\Enums\MatchingResultStatus;
use App\Models\ExceptionRecord;
use App\Models\Import;
use App\Models\MatchingResult;
use App\Models\Source;
use App\Models\Transaction;
use App\Services\DashboardMetricsService;

beforeEach(function () {
    $this->service = new DashboardMetricsService();
});

test('importStats groups by status and sums success/error rows', function () {
    Import::factory()->create(['status' => ImportStatus::Completed->value, 'success_rows' => 100, 'error_rows' => 0]);
    Import::factory()->create(['status' => ImportStatus::Completed->value, 'success_rows' => 50, 'error_rows' => 0]);
    Import::factory()->create(['status' => ImportStatus::Failed->value, 'success_rows' => 0, 'error_rows' => 10]);

    $stats = $this->service->importStats();

    expect($stats['by_status'][ImportStatus::Completed->value])->toBe(2);
    expect($stats['by_status'][ImportStatus::Failed->value])->toBe(1);
    expect($stats['success_rows'])->toBe(150);
    expect($stats['error_rows'])->toBe(10);
});

test('matchingStats groups MatchingResult counts by status', function () {
    MatchingResult::factory()->create(['status' => MatchingResultStatus::Matched->value]);
    MatchingResult::factory()->create(['status' => MatchingResultStatus::Matched->value]);
    MatchingResult::factory()->create(['status' => MatchingResultStatus::Conflict->value]);

    $stats = $this->service->matchingStats();

    expect($stats[MatchingResultStatus::Matched->value])->toBe(2);
    expect($stats[MatchingResultStatus::Conflict->value])->toBe(1);
});

test('exceptionStats groups by type and status, and exposes an open count', function () {
    ExceptionRecord::factory()->create(['type' => ExceptionType::Unmatched->value, 'status' => ExceptionStatus::Open->value]);
    ExceptionRecord::factory()->create(['type' => ExceptionType::Unmatched->value, 'status' => ExceptionStatus::Open->value]);
    ExceptionRecord::factory()->create(['type' => ExceptionType::Duplicate->value, 'status' => ExceptionStatus::Resolved->value]);

    $stats = $this->service->exceptionStats();

    expect($stats['by_type'][ExceptionType::Unmatched->value])->toBe(2);
    expect($stats['by_type'][ExceptionType::Duplicate->value])->toBe(1);
    expect($stats['by_status'][ExceptionStatus::Open->value])->toBe(2);
    expect($stats['open'])->toBe(2);
});

test('transactionVolumeBySource groups counts and amount sums per source', function () {
    $sourceA = Source::factory()->create(['code' => 'ALPHA']);
    $sourceB = Source::factory()->create(['code' => 'BNA']);
    Transaction::factory()->create(['source_id' => $sourceA->id, 'amount_millimes' => 10000]);
    Transaction::factory()->create(['source_id' => $sourceA->id, 'amount_millimes' => 20000]);
    Transaction::factory()->create(['source_id' => $sourceB->id, 'amount_millimes' => 5000]);

    $volumes = collect($this->service->transactionVolumeBySource())->keyBy('source_code');

    expect($volumes['ALPHA']['count'])->toBe(2);
    expect($volumes['ALPHA']['total_millimes'])->toBe(30000);
    expect($volumes['BNA']['count'])->toBe(1);
    expect($volumes['BNA']['total_millimes'])->toBe(5000);
});

test('dailyTransactionTrend returns one entry per day with correct counts, zero-filled', function () {
    $source = Source::factory()->create();
    Transaction::factory()->create(['source_id' => $source->id, 'transaction_date' => now()->format('Y-m-d')]);
    Transaction::factory()->create(['source_id' => $source->id, 'transaction_date' => now()->format('Y-m-d')]);

    $trend = $this->service->dailyTransactionTrend(7);

    expect($trend)->toHaveCount(7);
    $today = collect($trend)->firstWhere('date', now()->format('Y-m-d'));
    expect($today['count'])->toBe(2);
    $otherDays = collect($trend)->reject(fn ($row) => $row['date'] === now()->format('Y-m-d'));
    expect($otherDays->every(fn ($row) => $row['count'] === 0))->toBeTrue();
});

test('totalTransactions and activeSourceCount return plain counts', function () {
    $activeSource = Source::factory()->create(['is_active' => true]);
    Source::factory()->create(['is_active' => false]);
    Transaction::factory()->count(3)->create(['source_id' => $activeSource->id]);

    expect($this->service->totalTransactions())->toBe(3);
    expect($this->service->activeSourceCount())->toBe(1);
});

test('results are cached and reflect a value computed at first call time', function () {
    Import::factory()->create(['status' => ImportStatus::Completed->value]);

    $first = $this->service->importStats();
    Import::factory()->create(['status' => ImportStatus::Completed->value]);
    $second = $this->service->importStats();

    // still cached -- second call doesn't see the newly created row
    expect($second['by_status'][ImportStatus::Completed->value])->toBe($first['by_status'][ImportStatus::Completed->value]);
});
