<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! isset($argv[1])) {
    throw new InvalidArgumentException('Usage: php scripts/verify-renormalization.php backup.jsonl.gz');
}
$stream = gzopen($argv[1], 'rb');
if ($stream === false) {
    throw new RuntimeException('Sauvegarde illisible.');
}
$counts = [];
$batch = [];
$check = function (array $batch) use (&$counts): void {
    foreach (['transactions', 'normalized_transactions', 'import_rows', 'unmatched_snapshots'] as $table) {
        $entries = array_column($batch, $table);
        if ($entries === []) {
            continue;
        }
        $rows = DB::table($table)->whereIn('id', array_column(array_column($entries, 'before'), 'id'))->get()->keyBy('id');
        foreach ($entries as $entry) {
            $expected = array_replace($entry['before'], $entry['patch']);
            $actual = (array) $rows->get($expected['id']);
            unset($expected['updated_at'], $actual['updated_at']);
            foreach (['raw_payload', 'raw_data', 'transformed_data', 'normalized_data', 'result_a', 'result_b'] as $jsonField) {
                if (isset($expected[$jsonField])) {
                    $expected[$jsonField] = json_decode($expected[$jsonField], true, 512, JSON_THROW_ON_ERROR);
                }
                if (isset($actual[$jsonField])) {
                    $actual[$jsonField] = json_decode($actual[$jsonField], true, 512, JSON_THROW_ON_ERROR);
                }
            }
            if ($actual != $expected) {
                throw new RuntimeException("Écart de vérification : {$table} #{$expected['id']}");
            }
            $counts[$table] = ($counts[$table] ?? 0) + 1;
        }
    }
};
try {
    while (($line = gzgets($stream)) !== false) {
        $record = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        if (isset($record['rows'])) {
            $batch[] = $record['rows'];
        }
        if (count($batch) === 250) {
            $check($batch);
            $batch = [];
        }
    }
    $check($batch);
    if (($record['complete'] ?? false) !== true || ($counts['transactions'] ?? 0) !== $record['transactions']) {
        throw new RuntimeException('Vérification incomplète.');
    }
    echo json_encode(['verified' => $counts, 'all_fields_match_plan' => true], JSON_PRETTY_PRINT).PHP_EOL;
} finally {
    gzclose($stream);
}
