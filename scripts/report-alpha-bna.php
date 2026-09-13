<?php

use App\Models\Import;
use App\Models\UnmatchedSnapshot;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$latest = fn ($code) => Import::whereHas('source', fn ($q) => $q->where('code', $code))
    ->where('status', 'completed')->orderByDesc('created_at')->orderByDesc('id')->firstOrFail();
$imports = ['a' => $latest('ALPHA'), 'b' => $latest('BNA')];
$groups = [];
$report = ['generated_at' => now()->toIso8601String(), 'imports' => [], 'exact_groups' => 0, 'exact_rows_per_side' => 0,
    'exact_amount_millimes_per_side' => 0, 'conflicts' => [], 'exclusive' => ['a' => [], 'b' => []]];
foreach ($imports as $side => $import) {
    $rows = DB::table('normalized_transactions as n')->join('transactions as t', 't.id', '=', 'n.transaction_id')
        ->where('t.import_id', $import->id)->whereNull('n.deleted_at')->orderBy('n.id')
        ->get(['n.id', 'n.normalized_reference as reference', 'n.normalized_date as date', 'n.normalized_amount_millimes as amount_millimes', 't.raw_payload']);
    $report['imports'][$side] = ['id' => $import->id, 'file' => $import->original_filename,
        'rows' => $rows->count(), 'amount_millimes' => $rows->sum('amount_millimes'), 'missing_authorizations' => 0];
    foreach ($rows as $row) {
        $payload = json_decode($row->raw_payload, true, 512, JSON_THROW_ON_ERROR);
        $auth = trim((string) ($payload['num_autorisation'] ?? ''));
        if ($auth === '') {
            $report['imports'][$side]['missing_authorizations']++;
        }
        unset($row->raw_payload);
        $row->authorization = $auth;
        $key = $auth === '' ? 'missing:'.$row->id : 'auth:'.$auth;
        $groups[$key][$side][$row->date.'|'.$row->amount_millimes][] = (array) $row;
    }
}
// Independent multiset comparison: consume equal tuples, then classify remainders.
foreach ($groups as $key => $sides) {
    $remaining = ['a' => [], 'b' => []];
    $signatures = array_unique(array_merge(array_keys($sides['a'] ?? []), array_keys($sides['b'] ?? [])));
    foreach ($signatures as $signature) {
        $a = $sides['a'][$signature] ?? [];
        $b = $sides['b'][$signature] ?? [];
        $count = min(count($a), count($b));
        if ($count) {
            $report['exact_groups']++;
            $report['exact_rows_per_side'] += $count;
            $report['exact_amount_millimes_per_side'] += $count * $a[0]['amount_millimes'];
        }
        array_push($remaining['a'], ...array_slice($a, $count));
        array_push($remaining['b'], ...array_slice($b, $count));
    }
    if ($remaining['a'] && $remaining['b']) {
        $report['conflicts'][] = ['authorization' => substr($key, 5), 'rows' => $remaining];
    } else {
        foreach (['a', 'b'] as $side) {
            array_push($report['exclusive'][$side], ...$remaining[$side]);
        }
    }
}
$snapshot = UnmatchedSnapshot::where('import_a_id', $imports['a']->id)->where('import_b_id', $imports['b']->id)->first();
$report['snapshot'] = ['id' => $snapshot?->id, 'status' => $snapshot?->status, 'completed_at' => $snapshot?->completed_at?->toIso8601String()];
foreach (['a', 'b'] as $side) {
    $conflictRows = array_merge([], ...array_map(fn ($c) => $c['rows'][$side], $report['conflicts']));
    $exclusive = $report['exclusive'][$side];
    $summary = ['exclusive_rows' => count($exclusive), 'exclusive_amount_millimes' => array_sum(array_column($exclusive, 'amount_millimes')),
        'conflict_rows' => count($conflictRows), 'conflict_amount_millimes' => array_sum(array_column($conflictRows, 'amount_millimes'))];
    $summary['row_balance_ok'] = $report['imports'][$side]['rows'] === $report['exact_rows_per_side'] + count($exclusive) + count($conflictRows);
    $summary['amount_balance_ok'] = $report['imports'][$side]['amount_millimes'] === $report['exact_amount_millimes_per_side'] + $summary['exclusive_amount_millimes'] + $summary['conflict_amount_millimes'];
    $ids = array_column($exclusive, 'id');
    $saved = array_column($snapshot?->{'result_'.$side} ?? [], 'id');
    sort($ids);
    sort($saved);
    $summary['snapshot_ids_match'] = $ids === $saved;
    $report['checks'][$side] = $summary;
}
$directory = storage_path('app/reports/alpha-bna-'.date('Ymd-His'));
if (! is_dir($directory) && ! mkdir($directory, 0770, true)) {
    throw new RuntimeException('Impossible de créer le dossier du rapport.');
}
file_put_contents($directory.'/report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
foreach (['a' => 'alpha', 'b' => 'bna'] as $side => $name) {
    $stream = fopen($directory.'/exclusives-'.$name.'.csv', 'wb');
    fputcsv($stream, ['id', 'reference', 'date', 'amount_millimes', 'authorization'], ';', '"', '');
    foreach ($report['exclusive'][$side] as $row) {
        fputcsv($stream, array_values($row), ';', '"', '');
    }
    fclose($stream);
}
unset($report['exclusive']);
echo json_encode(['directory' => $directory, 'report' => $report], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
