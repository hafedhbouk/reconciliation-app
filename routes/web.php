<?php

/*
|--------------------------------------------------------------------------
| Routes web : la « table des matières » de l'application
|--------------------------------------------------------------------------
| Chaque ligne associe une URL (et une méthode HTTP) à une méthode de contrôleur :
|   Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
|     - get / post / put / patch / delete : la méthode HTTP
|       (GET = afficher, POST = créer ou agir, PUT/PATCH = modifier, DELETE = supprimer) ;
|     - ->name('dashboard') : un nom pour générer l'URL ailleurs avec route('dashboard').
|
| Route::resource('banks', BankController::class) crée d'un coup les 7 routes
| CRUD classiques (index, create, store, show, edit, update, destroy) ;
| ->except('show') ou ->only([...]) en retire ou en garde certaines.
|
| Middlewares utilisés (filtres appliqués avant le contrôleur) :
|   'auth'     : il faut être connecté, sinon redirection vers la page de connexion ;
|   'verified' : l'adresse email doit être vérifiée ;
|   'throttle:expensive-actions' : 10 actions coûteuses par minute au maximum
|                (limiteur défini dans AppServiceProvider).
|
| Astuce : `php artisan route:list` affiche toutes les routes de l'application.
*/

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BankController;
use App\Http\Controllers\Admin\CurrencyController;
use App\Http\Controllers\Admin\ExceptionAttachmentController;
use App\Http\Controllers\Admin\ExceptionController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\MatchingResultController;
use App\Http\Controllers\Admin\MatchingRuleController;
use App\Http\Controllers\Admin\OperationController;
use App\Http\Controllers\Admin\OperationEmailController;
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

// Page d'accueil « / » : tableau de bord si connecté, sinon page de connexion.
Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');
// Données du tableau de bord analytique (filtres dynamiques), relues en JSON par resources/js/dashboard.js.
Route::get('/dashboard/analytics', [DashboardController::class, 'analytics'])->middleware(['auth', 'verified'])->name('dashboard.analytics');

// Pages réservées aux utilisateurs connectés : profil et notifications.
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
});

// Toute l'administration : URL commençant par /admin, noms de route commençant par « admin. »
// (ex. la route 'admin.imports.show' correspond à l'URL /admin/imports/{import}).
// Les droits fins (qui peut voir ou modifier quoi) sont ensuite vérifiés par les Policies.
Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    // --- Référentiels ---
    Route::resource('banks', BankController::class)->except('show');
    Route::resource('sources', SourceController::class)->except('show');
    Route::get('sources/{source}/mappings', [SourceMappingController::class, 'edit'])->name('sources.mappings.edit');
    Route::put('sources/{source}/mappings', [SourceMappingController::class, 'update'])->name('sources.mappings.update');
    Route::resource('currencies', CurrencyController::class)->except('show');
    Route::resource('settings', SettingController::class)->only(['index', 'edit', 'update']);

    // --- Utilisateurs, rôles, audit ---
    // Important : les routes « .../data » sont déclarées AVANT le resource, sinon
    // « users/data » serait compris comme « users/{user} » avec user = "data".
    Route::get('users/data', [UserController::class, 'data'])->name('users.data');
    Route::resource('users', UserController::class)->except('show');

    Route::resource('roles', RoleController::class)->except('show');

    Route::get('audit-logs/data', [AuditLogController::class, 'data'])->name('audit-logs.data');
    Route::resource('audit-logs', AuditLogController::class)->only(['index', 'show']);

    // --- Imports de fichiers ---
    Route::get('imports/data', [ImportController::class, 'data'])->name('imports.data');
    Route::resource('imports', ImportController::class)->only(['index', 'create', 'store', 'show', 'destroy']);
    Route::post('imports/{import}/process', [ImportController::class, 'process'])->name('imports.process');

    // --- Règles et lancement des rapprochements (actions coûteuses : limitées) ---
    Route::get('matching-rules/data', [MatchingRuleController::class, 'data'])->name('matching-rules.data');
    Route::get('matching-rules/maintenance', [MatchingRuleController::class, 'maintenance'])->name('matching-rules.maintenance');
    Route::post('matching-rules/run-all', [MatchingRuleController::class, 'runAll'])->middleware('throttle:expensive-actions')->name('matching-rules.run-all');
    Route::post('matching-rules/detect-duplicates', [MatchingRuleController::class, 'detectDuplicates'])->middleware('throttle:expensive-actions')->name('matching-rules.detect-duplicates');
    Route::post('matching-rules/sweep-unmatched', [MatchingRuleController::class, 'sweepUnmatched'])->middleware('throttle:expensive-actions')->name('matching-rules.sweep-unmatched');
    Route::post('matching-rules/{matching_rule}/run', [MatchingRuleController::class, 'run'])->middleware('throttle:expensive-actions')->name('matching-rules.run');
    Route::post('matching-rules/run-ad-hoc', [MatchingRuleController::class, 'runAdHoc'])->middleware('throttle:expensive-actions')->name('matching-rules.run-ad-hoc');
    Route::resource('matching-rules', MatchingRuleController::class)->except('show');

    // --- Résultats de rapprochement et exports ---
    // Le lien de téléchargement contient un jeton aléatoire ET exige d'être connecté
    // (il est dans ce groupe 'auth').
    Route::get('matching-results/data', [MatchingResultController::class, 'data'])->name('matching-results.data');
    Route::get('matching-results/export/{format}', [MatchingResultController::class, 'export'])->middleware('throttle:expensive-actions')->name('matching-results.export');
    Route::post('matching-results/export-async', [MatchingResultController::class, 'exportAsync'])->middleware('throttle:expensive-actions')->name('matching-results.export-async');
    Route::get('matching-results/exports', [MatchingResultController::class, 'exports'])->name('matching-results.exports');
    Route::get('matching-results/exports/{token}/download', [MatchingResultController::class, 'downloadExport'])->name('matching-results.exports.download');
    Route::resource('matching-results', MatchingResultController::class)->only(['index', 'show', 'destroy']); // destroy permet de supprimer un résultat erroné

    // --- Rapprochement manuel et écarts entre fichiers ---
    Route::get('reconciliation', [ReconciliationController::class, 'index'])->name('reconciliation.index');
    Route::get('reconciliation/search', [ReconciliationController::class, 'search'])->name('reconciliation.search');
    Route::post('reconciliation', [ReconciliationController::class, 'store'])->name('reconciliation.store');
    // --- Suivi des opérations longues (import, rapprochement, comparaison, exports) ---
    Route::get('operations', [OperationController::class, 'index'])->name('operations.index');
    Route::get('operations/data', [OperationController::class, 'data'])->name('operations.data');
    Route::get('operations/{operation}', [OperationController::class, 'show'])->name('operations.show');
    // --- Envoi des résultats d'un rapprochement par e-mail ---
    Route::get('operations/{operation}/email', [OperationEmailController::class, 'create'])->name('operations.email');
    Route::post('operations/{operation}/email', [OperationEmailController::class, 'store'])->middleware('throttle:expensive-actions')->name('operations.email.store');
    Route::post('result-emails/{resultEmail}/retry', [OperationEmailController::class, 'retry'])->middleware('throttle:expensive-actions')->name('result-emails.retry');
    Route::get('reconciliation/unmatched', [ReconciliationController::class, 'unmatched'])->name('reconciliation.unmatched');
    Route::post('reconciliation/unmatched/{snapshot}/export-async', [ReconciliationController::class, 'exportAsync'])->middleware('throttle:expensive-actions')->name('reconciliation.unmatched.export-async');
    Route::post('reconciliation/unmatched/refresh', [ReconciliationController::class, 'refreshUnmatched'])->name('reconciliation.unmatched.refresh');

    // --- Exceptions (anomalies) et pièces jointes ---
    Route::get('exceptions/data', [ExceptionController::class, 'data'])->name('exceptions.data');
    Route::get('exceptions/export/{format}', [ExceptionController::class, 'export'])->middleware('throttle:expensive-actions')->name('exceptions.export');
    Route::resource('exceptions', ExceptionController::class)->only(['index', 'show', 'update']);
    Route::post('exceptions/{exception}/attachments', [ExceptionAttachmentController::class, 'store'])->name('exceptions.attachments.store');
    Route::get('exceptions/{exception}/attachments/{attachment}/download', [ExceptionAttachmentController::class, 'download'])->name('exceptions.attachments.download');
    Route::delete('exceptions/{exception}/attachments/{attachment}', [ExceptionAttachmentController::class, 'destroy'])->name('exceptions.attachments.destroy');

    // --- Recherche multicritère ---
    Route::get('search', [SearchController::class, 'index'])->name('search.index');
    Route::get('search/data', [SearchController::class, 'data'])->name('search.data');
    Route::get('search/export/{format}', [SearchController::class, 'export'])->middleware('throttle:expensive-actions')->name('search.export');
    Route::post('search/export-async', [SearchController::class, 'exportAsync'])->middleware('throttle:expensive-actions')->name('search.export-async');
    Route::get('search/exports', [SearchController::class, 'exports'])->name('search.exports');
    Route::get('search/exports/{token}/download', [SearchController::class, 'downloadExport'])->name('search.exports.download');
});

// Routes de connexion, inscription et mot de passe oublié (Laravel Breeze).
require __DIR__.'/auth.php';
