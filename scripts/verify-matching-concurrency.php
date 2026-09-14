<?php

// Integration check using an isolated, disposable MySQL database. Never migrate
// or seed the configured application database. Requires CREATE DATABASE rights.
use App\Enums\MatchingStatus;
use App\Http\Controllers\Admin\ReconciliationController;
use App\Http\Requests\Admin\StoreManualMatchRequest;
use App\Jobs\ProcessImportJob;
use App\Jobs\RunAdHocMatchingJob;
use App\Models\ComparisonRun;
use App\Models\Import;
use App\Models\ImportRow;
use App\Models\MatchingResult;
use App\Models\NormalizedTransaction;
use App\Models\Source;
use App\Models\SourceColumnMapping;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Import\MappingEngine;
use App\Services\Import\Readers\ImportRowReaderFactory;
use App\Services\Import\TransactionNormalizer;
use App\Services\Matching\RuleMatcher;
use App\Services\Matching\TransactionStatus;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $exception): void {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    exit(1);
});

function useTestDatabase(string $name): void
{
    if (! preg_match('/^reconciliation_lock_test_[a-f0-9]{12}$/D', $name)) {
        throw new RuntimeException('Invalid isolated database name.');
    }
    config(['database.default' => 'mysql', 'database.connections.mysql.database' => $name,
        'database.connections.mysql.url' => null, 'cache.default' => 'array', 'session.driver' => 'array']);
    DB::purge('mysql');
}

if (($argv[1] ?? '') === 'worker') {
    useTestDatabase($argv[2]);
    $mode = $argv[3];
    $first = (int) $argv[4];
    $second = (int) $argv[5];
    $hold = ($argv[6] ?? '') === 'hold';
    Auth::login(User::firstOrFail());
    try {
        DB::transaction(function () use ($mode, $first, $second, $hold) {
            if ($hold) {
                if ($mode === 'manual') {
                    app(TransactionStatus::class)->lock(collect([$first, $second]));
                } else {
                    Import::whereIn('id', [$first, $second])->orderBy('id')->lockForUpdate()->get();
                }
                fwrite(STDOUT, "locked\n");
                fflush(STDOUT);
                usleep(2000000);
            }
            if ($mode === 'manual') {
                $request = StoreManualMatchRequest::create('/', 'POST', [
                    'normalized_transaction_ids_a' => [$first], 'normalized_transaction_ids_b' => [$second],
                ]);
                $request->setUserResolver(fn () => Auth::user());
                app(ReconciliationController::class)->store($request);
            } elseif ($mode === 'import') {
                (new ProcessImportJob($first))->handle(app(ImportRowReaderFactory::class),
                    app(MappingEngine::class), app(TransactionNormalizer::class));
            } else {
                (new RunAdHocMatchingJob($first, $second, 'concurrency-test'))->handle(app(RuleMatcher::class));
            }
        });
        echo "completed\n";
    } catch (ValidationException $exception) {
        echo "unavailable\n";
    }
    exit;
}

function startWorker(string $database, string $mode, int $a, int $b, bool $hold): array
{
    $process = proc_open([PHP_BINARY, __FILE__, 'worker', $database, $mode, (string) $a, (string) $b, $hold ? 'hold' : 'run'],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));
    if (! is_resource($process)) {
        throw new RuntimeException('Cannot start PHP worker.');
    }
    fclose($pipes[0]);

    return [$process, $pipes];
}

function finishWorker(array $worker): string
{
    [$process, $pipes] = $worker;
    $output = trim(stream_get_contents($pipes[1]));
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('Worker failed: '.$error);
    }

    return $output;
}

function simultaneous(string $database, string $mode, int $a, int $b): array
{
    $first = startWorker($database, $mode, $a, $b, true);
    if (trim(fgets($first[1][1]) ?: '') !== 'locked') {
        throw new RuntimeException('First worker failed to acquire the lock: '.finishWorker($first));
    }
    $second = startWorker($database, $mode, $a, $b, false);

    return [finishWorker($first), finishWorker($second)];
}

function check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

$database = 'reconciliation_lock_test_'.bin2hex(random_bytes(6));
$admin = DB::connection('mysql')->getPdo();
$admin->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$importPath = 'testing/'.$database.'.csv';
try {
    useTestDatabase($database);
    check(Artisan::call('migrate', ['--force' => true]) === 0, 'Isolated migration failed.');
    Artisan::call('db:seed', ['--class' => RolePermissionSeeder::class, '--force' => true]);
    User::factory()->create()->assignRole('admin');
    $a = NormalizedTransaction::factory()->create(['matching_status' => MatchingStatus::Unmatched]);
    $b = NormalizedTransaction::factory()->create(['matching_status' => MatchingStatus::Unmatched]);
    $manual = simultaneous($database, 'manual', $a->id, $b->id);
    $outcomes = $manual;
    sort($outcomes);
    check($outcomes === ['completed', 'unavailable'], 'Concurrent manual selection was not rejected: '.json_encode($manual));
    check(MatchingResult::count() === 1, 'Duplicate manual result.');

    $imports = [];
    foreach (['ALPHA', 'BNA'] as $code) {
        $source = Source::factory()->create(['code' => $code]);
        $import = Import::factory()->create(['source_id' => $source->id, 'status' => 'completed']);
        $transaction = Transaction::factory()->create(['source_id' => $source->id, 'import_id' => $import->id,
            'raw_payload' => ['num_autorisation' => '001234']]);
        NormalizedTransaction::factory()->create(['transaction_id' => $transaction->id,
            'normalized_date' => '2026-05-01', 'normalized_amount_millimes' => 10000, 'matching_status' => 'unmatched']);
        $imports[] = $import->id;
    }
    $files = simultaneous($database, 'files', ...$imports);
    check($files === ['completed', 'completed'], 'Concurrent file run failed.');
    check(ComparisonRun::count() === 1 && MatchingResult::count() === 2, 'File replay created duplicate results.');
    DB::beginTransaction();
    try {
        (new RunAdHocMatchingJob($imports[0], $imports[1], 'rollback-check'))->handle(app(RuleMatcher::class));
        check(DB::connection()->getPdo()->inTransaction(), 'Temporary key indexing committed the outer transaction.');
    } finally {
        DB::rollBack();
    }
    check(ComparisonRun::count() === 1 && MatchingResult::count() === 2, 'Rollback left comparison results behind.');
    $source = Source::factory()->create(['code' => 'TEST_IMPORT', 'file_type' => 'csv']);
    foreach (['reference', 'amount', 'date'] as $field) {
        SourceColumnMapping::create(['source_id' => $source->id, 'target_field' => $field, 'source_column' => $field, 'is_required' => true, 'transform' => []]);
    }
    $csv = "reference,amount,date\none,1000,2026-05-01\n,2000,2026-05-01\nthree,3000,2026-05-01\n";
    Storage::put($importPath, $csv);
    $import = Import::factory()->create(['source_id' => $source->id, 'stored_path' => $importPath, 'status' => 'pending',
        'file_hash' => hash('sha256', $csv), 'imported_by' => User::firstOrFail()->id]);
    $importResults = simultaneous($database, 'import', $import->id, 0);
    check($importResults === ['completed', 'completed'], 'Concurrent import failed.');
    check(ImportRow::where('import_id', $import->id)->count() === 3 && $import->fresh()->success_rows === 2, 'Import replay duplicated rows or counters.');
    echo json_encode(['manual' => $manual, 'file_replay' => $files, 'import_replay' => $importResults, 'comparison_runs' => ComparisonRun::count(),
        'result' => 'passed'], JSON_PRETTY_PRINT).PHP_EOL;
} finally {
    Storage::delete($importPath);
    DB::purge('mysql');
    // The name is generated above, never supplied by the caller.
    $admin->exec("DROP DATABASE `$database`");
}
