<?php

use App\Models\Import;
use App\Services\Import\ImportRenormalizer;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$service = app(ImportRenormalizer::class);
$progress = fn ($message) => print $message.PHP_EOL;
$mode = $argv[1] ?? 'prepare';
if ($mode === 'prepare') {
    $ids = Import::whereHas('source', fn ($q) => $q->whereIn('code', ['ALPHA', 'BNA']))
        ->whereIn('status', ['completed', 'partially_completed'])->orderBy('id')->pluck('id')->all();
    $path = storage_path('app/renormalize-alpha-bna-'.date('Ymd-His').'.jsonl.gz');
    $summary = $service->prepare($ids, $path, $progress);
    echo json_encode(['backup' => $path, 'imports' => $summary], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
} elseif ($mode === 'apply' && isset($argv[2])) {
    echo 'COMMITTED: '.$service->apply($argv[2], $progress).' transactions'.PHP_EOL;
} else {
    throw new InvalidArgumentException('Usage: php scripts/renormalize-alpha-bna.php prepare|apply [backup.jsonl.gz]');
}
