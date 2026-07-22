<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BankController;
use App\Http\Controllers\Admin\CurrencyController;
use App\Http\Controllers\Admin\HolidayController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SourceController;
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
    Route::resource('currencies', CurrencyController::class)->except('show');
    Route::resource('holidays', HolidayController::class)->except('show');
    Route::resource('settings', SettingController::class)->only(['index', 'edit', 'update']);

    Route::get('users/data', [UserController::class, 'data'])->name('users.data');
    Route::resource('users', UserController::class)->except('show');

    Route::resource('roles', RoleController::class)->except('show');

    Route::get('audit-logs/data', [AuditLogController::class, 'data'])->name('audit-logs.data');
    Route::resource('audit-logs', AuditLogController::class)->only(['index', 'show']);
});

require __DIR__.'/auth.php';
