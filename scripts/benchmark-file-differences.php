<?php

use App\Models\Import;
use App\Services\Matching\RuleMatcher;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $exception): void {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    exit(1);
});

$a = Import::with('source')->findOrFail((int) ($argv[1] ?? 0));
$b = Import::with('source')->findOrFail((int) ($argv[2] ?? 0));
DB::disableQueryLog();
memory_reset_peak_usage();
$started = microtime(true);
DB::beginTransaction();
try {
    $summary = app(RuleMatcher::class)->refreshFileDifferences($a, $b);
    $report = [
        'imports' => [$a->id, $b->id], 'sources' => [$a->source->code, $b->source->code],
        'summary' => (array) $summary,
        'seconds' => round(microtime(true) - $started, 3),
        'peak_php_memory_mib' => round(memory_get_peak_usage(true) / 1048576, 2),
        'persisted' => false,
    ];
} finally {
    DB::rollBack();
}
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
