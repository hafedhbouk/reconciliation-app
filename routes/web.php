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
use App\Http\Controllers\Admin\SearchController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SourceController;
use App\Http\Controllers\Admin\SourceMappingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
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
    Route::resource('imports', ImportController::class)->only(['index', 'create', 'store', 'show', 'destroy']);
    Route::post('imports/{import}/process', [ImportController::class, 'process'])->name('imports.process');

    Route::get('matching-rules/data', [MatchingRuleController::class, 'data'])->name('matching-rules.data');
    Route::post('matching-rules/run-all', [MatchingRuleController::class, 'runAll'])->middleware('throttle:expensive-actions')->name('matching-rules.run-all');
    Route::post('matching-rules/detect-duplicates', [MatchingRuleController::class, 'detectDuplicates'])->middleware('throttle:expensive-actions')->name('matching-rules.detect-duplicates');
    Route::post('matching-rules/sweep-unmatched', [MatchingRuleController::class, 'sweepUnmatched'])->middleware('throttle:expensive-actions')->name('matching-rules.sweep-unmatched');
    Route::post('matching-rules/{matching_rule}/run', [MatchingRuleController::class, 'run'])->middleware('throttle:expensive-actions')->name('matching-rules.run');
    Route::post('matching-rules/run-ad-hoc', [MatchingRuleController::class, 'runAdHoc'])->middleware('throttle:expensive-actions')->name('matching-rules.run-ad-hoc');
    Route::resource('matching-rules', MatchingRuleController::class)->except('show');

    Route::get('matching-results/data', [MatchingResultController::class, 'data'])->name('matching-results.data');
    Route::get('matching-results/export/{format}', [MatchingResultController::class, 'export'])->middleware('throttle:expensive-actions')->name('matching-results.export');
    Route::post('matching-results/export-async', [MatchingResultController::class, 'exportAsync'])->middleware('throttle:expensive-actions')->name('matching-results.export-async');
    Route::get('matching-results/exports', [MatchingResultController::class, 'exports'])->name('matching-results.exports');
    Route::get('matching-results/exports/{token}/download', [MatchingResultController::class, 'downloadExport'])->name('matching-results.exports.download');
    Route::resource('matching-results', MatchingResultController::class)->only(['index', 'show', 'destroy']); // destroy permet de supprimer un résultat erroné

    Route::get('reconciliation', [ReconciliationController::class, 'index'])->name('reconciliation.index');
    Route::get('reconciliation/search', [ReconciliationController::class, 'search'])->name('reconciliation.search');
    Route::post('reconciliation', [ReconciliationController::class, 'store'])->name('reconciliation.store');
    Route::get('reconciliation/unmatched', [ReconciliationController::class, 'unmatched'])->name('reconciliation.unmatched');
    Route::post('reconciliation/unmatched/refresh', [ReconciliationController::class, 'refreshUnmatched'])->name('reconciliation.unmatched.refresh');

    Route::get('exceptions/data', [ExceptionController::class, 'data'])->name('exceptions.data');
    Route::get('exceptions/export/{format}', [ExceptionController::class, 'export'])->middleware('throttle:expensive-actions')->name('exceptions.export');
    Route::resource('exceptions', ExceptionController::class)->only(['index', 'show', 'update']);
    Route::post('exceptions/{exception}/attachments', [ExceptionAttachmentController::class, 'store'])->name('exceptions.attachments.store');
    Route::get('exceptions/{exception}/attachments/{attachment}/download', [ExceptionAttachmentController::class, 'download'])->name('exceptions.attachments.download');
    Route::delete('exceptions/{exception}/attachments/{attachment}', [ExceptionAttachmentController::class, 'destroy'])->name('exceptions.attachments.destroy');

    Route::get('search', [SearchController::class, 'index'])->name('search.index');
    Route::get('search/data', [SearchController::class, 'data'])->name('search.data');
    Route::get('search/export/{format}', [SearchController::class, 'export'])->middleware('throttle:expensive-actions')->name('search.export');
});

require __DIR__.'/auth.php';
