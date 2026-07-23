<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BankController;
use App\Http\Controllers\Admin\CurrencyController;
use App\Http\Controllers\Admin\ExceptionAttachmentController;
use App\Http\Controllers\Admin\ExceptionController;
use App\Http\Controllers\Admin\HolidayController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\MatchingResultController;
use App\Http\Controllers\Admin\MatchingRuleController;
use App\Http\Controllers\Admin\ReconciliationController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SourceController;
use App\Http\Controllers\Admin\SourceMappingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('banks', BankController::class)->except('show');
    Route::resource('sources', SourceController::class)->except('show');
    Route::get('sources/{source}/mappings', [SourceMappingController::class, 'edit'])->name('sources.mappings.edit');
    Route::put('sources/{source}/mappings', [SourceMappingController::class, 'update'])->name('sources.mappings.update');
    Route::resource('currencies', CurrencyController::class)->except('show');
    Route::resource('holidays', HolidayController::class)->except('show');
    Route::resource('settings', SettingController::class)->only(['index', 'edit', 'update']);

    Route::get('users/data', [UserController::class, 'data'])->name('users.data');
    Route::resource('users', UserController::class)->except('show');

    Route::resource('roles', RoleController::class)->except('show');

    Route::get('audit-logs/data', [AuditLogController::class, 'data'])->name('audit-logs.data');
    Route::resource('audit-logs', AuditLogController::class)->only(['index', 'show']);

    Route::get('imports/data', [ImportController::class, 'data'])->name('imports.data');
    Route::resource('imports', ImportController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('imports/{import}/process', [ImportController::class, 'process'])->name('imports.process');

    Route::get('matching-rules/data', [MatchingRuleController::class, 'data'])->name('matching-rules.data');
    Route::post('matching-rules/run-all', [MatchingRuleController::class, 'runAll'])->name('matching-rules.run-all');
    Route::post('matching-rules/detect-duplicates', [MatchingRuleController::class, 'detectDuplicates'])->name('matching-rules.detect-duplicates');
    Route::post('matching-rules/sweep-unmatched', [MatchingRuleController::class, 'sweepUnmatched'])->name('matching-rules.sweep-unmatched');
    Route::post('matching-rules/{matching_rule}/run', [MatchingRuleController::class, 'run'])->name('matching-rules.run');
    Route::resource('matching-rules', MatchingRuleController::class)->except('show');

    Route::get('matching-results/data', [MatchingResultController::class, 'data'])->name('matching-results.data');
    Route::resource('matching-results', MatchingResultController::class)->only(['index', 'show']);

    Route::get('reconciliation', [ReconciliationController::class, 'index'])->name('reconciliation.index');
    Route::get('reconciliation/search', [ReconciliationController::class, 'search'])->name('reconciliation.search');
    Route::post('reconciliation', [ReconciliationController::class, 'store'])->name('reconciliation.store');

    Route::get('exceptions/data', [ExceptionController::class, 'data'])->name('exceptions.data');
    Route::resource('exceptions', ExceptionController::class)->only(['index', 'show', 'update']);
    Route::post('exceptions/{exception}/attachments', [ExceptionAttachmentController::class, 'store'])->name('exceptions.attachments.store');
    Route::get('exceptions/{exception}/attachments/{attachment}/download', [ExceptionAttachmentController::class, 'download'])->name('exceptions.attachments.download');
    Route::delete('exceptions/{exception}/attachments/{attachment}', [ExceptionAttachmentController::class, 'destroy'])->name('exceptions.attachments.destroy');
});

require __DIR__.'/auth.php';
