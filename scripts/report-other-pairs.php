<?php

use App\Models\Import;
use App\Models\SourceColumnMapping;
use App\Models\UnmatchedSnapshot;
use App\Services\Import\MappingEngine;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$directory = storage_path('app/reports/autres-combinaisons-'.date('Ymd-His'));
if (! mkdir($directory, 0770, true)) {
    throw new RuntimeException('Impossible de créer le dossier du rapport.');
}
$data = [];
$quality = [];
$imports = [];
$engine = app(MappingEngine::class);
foreach (['ALPHA', 'WEB', 'SMT', 'BNA'] as $code) {
    $import = Import::whereHas('source', fn ($q) => $q->whereIn('code', $code === 'WEB' ? ['WEB', 'STEG'] : [$code]))
        ->where('status', 'completed')->orderByDesc('created_at')->orderByDesc('id')->firstOrFail();
    $imports[$code] = ['id' => $import->id, 'file' => $import->original_filename];
    $mappings = SourceColumnMapping::where('source_id', $import->source_id)->orderBy('sort_order')->get();
    $quality[$code] = ['rows' => 0, 'invalid_original_rows' => 0, 'changed_date' => 0, 'changed_amount' => 0, 'changed_reference' => 0, 'changed_authorization' => 0];
    $data[$code] = ['stored' => [], 'original' => []];
    DB::table('normalized_transactions as n')->join('transactions as t', 't.id', '=', 'n.transaction_id')
        ->leftJoin('import_rows as r', 'r.id', '=', 't.import_row_id')
        ->where('t.import_id', $import->id)->whereNull('n.deleted_at')->orderBy('n.id')
        ->select(['n.id', 'n.normalized_reference', 'n.normalized_date', 'n.normalized_amount_millimes', 't.raw_payload', 'r.raw_data', 'r.row_number'])
        ->chunkById(250, function ($rows) use ($code, $mappings, $engine, &$quality, &$data) {
            foreach ($rows as $row) {
                $quality[$code]['rows']++;
                $payload = json_decode($row->raw_payload, true, 512, JSON_THROW_ON_ERROR);
                $field = $code === 'WEB' ? 'secondary_reference' : 'num_autorisation';
                $stored = ['id' => $row->id, 'source_row' => $row->row_number, 'reference' => $row->normalized_reference,
                    'authorization' => $code === 'SMT' ? null : ($payload[$field] ?? null),
                    'date' => $row->normalized_date, 'amount' => (int) $row->normalized_amount_millimes];
                try {
                    if ($row->raw_data === null) {
                        throw new RuntimeException('Données originales absentes.');
                    }
                    $mapped = $engine->transformRow(json_decode($row->raw_data, true, 512, JSON_THROW_ON_ERROR), $mappings);
                    $original = ['id' => $row->id, 'source_row' => $row->row_number,
                        'reference' => $mapped['reference'] ?? null,
                        'authorization' => $code === 'SMT' ? null : ($mapped[$field] ?? null),
                        'date' => $mapped['date'], 'amount' => (int) $mapped['amount']];
                    foreach (['date', 'amount', 'reference', 'authorization'] as $attribute) {
                        // BNA/SMT have no business reference to compare.
                        if ($attribute === 'reference' && in_array($code, ['BNA', 'SMT'])) {
                            continue;
                        }
                        if ($original[$attribute] != $stored[$attribute]) {
                            $quality[$code]['changed_'.$attribute]++;
                        }
                    }
                    $data[$code]['original'][] = $original;
                } catch (Throwable $e) {
                    $quality[$code]['invalid_original_rows']++;
                    $quality[$code]['error_examples'][] = ['id' => $row->id, 'error' => $e->getMessage()];
                }
                $data[$code]['stored'][] = $stored;
            }
        }, 'n.id', 'id');
    echo $code.' : '.json_encode($quality[$code]).PHP_EOL;
}

function comparePair(array $a, array $b, string $codeA, string $codeB): array
{
    $smt = in_array('SMT', [$codeA, $codeB]);
    $alphaWeb = in_array('ALPHA', [$codeA, $codeB]) && in_array('WEB', [$codeA, $codeB]);
    $groups = [];
    foreach (['a' => $a, 'b' => $b] as $side => $rows) {
        foreach ($rows as $row) {
            $identity = $smt ? [$row['date'], $row['amount']] : [$row[$alphaWeb ? 'reference' : 'authorization']];
            $missing = in_array(null, $identity, true) || in_array('', $identity, true);
            $key = $missing ? 'missing:'.$row['id'] : json_encode($identity);
            $tuple = [$row['date'], $row['amount']];
            if ($alphaWeb) {
                $tuple[] = $row['authorization'];
            }
            $signature = in_array(null, $tuple, true) || in_array('', $tuple, true) ? 'missing:'.$row['id'] : json_encode($tuple);
            $groups[$key][$side][$signature][] = $row;
        }
    }
    $result = ['exact_rows_per_side' => 0, 'exact_groups' => 0, 'exact_amount' => 0, 'conflicts' => [], 'exclusive' => ['a' => [], 'b' => []]];
    foreach ($groups as $key => $sides) {
        $remaining = ['a' => [], 'b' => []];
        foreach (array_unique(array_merge(array_keys($sides['a'] ?? []), array_keys($sides['b'] ?? []))) as $signature) {
            $rowsA = $sides['a'][$signature] ?? [];
            $rowsB = $sides['b'][$signature] ?? [];
            $count = min(count($rowsA), count($rowsB));
            if ($count > 0) {
                $result['exact_rows_per_side'] += $count;
                $result['exact_groups']++;
                $result['exact_amount'] += $count * $rowsA[0]['amount'];
            }
            array_push($remaining['a'], ...array_slice($rowsA, $count));
            array_push($remaining['b'], ...array_slice($rowsB, $count));
        }
        if ($remaining['a'] && $remaining['b']) {
            $result['conflicts'][] = ['key' => $key, 'rows' => $remaining];
        } else {
            foreach (['a', 'b'] as $side) {
                array_push($result['exclusive'][$side], ...$remaining[$side]);
            }
        }
    }
    foreach (['a' => $a, 'b' => $b] as $side => $rows) {
        $conflicts = array_merge([], ...array_map(fn ($c) => $c['rows'][$side], $result['conflicts']));
        $exclusive = $result['exclusive'][$side];
        $result['summary'][$side] = ['total_rows' => count($rows), 'total_amount' => array_sum(array_column($rows, 'amount')),
            'conflict_rows' => count($conflicts), 'conflict_amount' => array_sum(array_column($conflicts, 'amount')),
            'exclusive_rows' => count($exclusive), 'exclusive_amount' => array_sum(array_column($exclusive, 'amount'))];
        $result['summary'][$side]['row_balance_ok'] = count($rows) === $result['exact_rows_per_side'] + count($conflicts) + count($exclusive);
        $result['summary'][$side]['amount_balance_ok'] = $result['summary'][$side]['total_amount'] === $result['exact_amount'] + $result['summary'][$side]['conflict_amount'] + $result['summary'][$side]['exclusive_amount'];
        if (! $result['summary'][$side]['row_balance_ok'] || ! $result['summary'][$side]['amount_balance_ok']) {
            throw new RuntimeException('Échec du contrôle des totaux.');
        }
    }

    return $result;
}

$pairs = [];
foreach ([['ALPHA', 'WEB'], ['ALPHA', 'SMT'], ['SMT', 'BNA'], ['WEB', 'SMT'], ['WEB', 'BNA']] as [$a, $b]) {
    $name = $a.'-'.$b;
    $current = comparePair($data[$a]['stored'], $data[$b]['stored'], $a, $b);
    $original = comparePair($data[$a]['original'], $data[$b]['original'], $a, $b);
    $snapshot = UnmatchedSnapshot::where('import_a_id', $imports[$a]['id'])->where('import_b_id', $imports[$b]['id'])->first();
    $pair = ['sources' => [$a, $b], 'imports' => [$imports[$a], $imports[$b]], 'stored' => $current, 'original' => $original,
        'snapshot' => ['id' => $snapshot?->id, 'status' => $snapshot?->status]];
    foreach (['a', 'b'] as $side) {
        $ids = array_column($original['exclusive'][$side], 'id');
        $saved = array_column($snapshot?->{'result_'.$side} ?? [], 'id');
        sort($ids);
        sort($saved);
        $pair['snapshot']['ids_match_'.$side] = $snapshot && $snapshot->status === 'completed' ? $ids === $saved : null;
        $stream = fopen($directory.'/'.$name.'-exclusives-'.$side.'.csv', 'wb');
        fputcsv($stream, ['id', 'source_row', 'reference', 'authorization', 'date', 'amount_millimes'], ';', '"', '');
        foreach ($original['exclusive'][$side] as $row) {
            fputcsv($stream, array_values($row), ';', '"', '');
        }
        fclose($stream);
    }
    $stream = fopen($directory.'/'.$name.'-conflits.csv', 'wb');
    fputcsv($stream, ['group', 'side', 'id', 'source_row', 'reference', 'authorization', 'date', 'amount_millimes'], ';', '"', '');
    foreach ($original['conflicts'] as $index => $conflict) {
        foreach ($conflict['rows'] as $side => $rows) {
            foreach ($rows as $row) {
                fputcsv($stream, [$index + 1, $side, ...array_values($row)], ';', '"', '');
            }
        }
    }
    fclose($stream);
    file_put_contents($directory.'/'.$name.'.json', json_encode($pair, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    foreach (['stored', 'original'] as $mode) {
        $pair[$mode]['conflict_groups'] = count($pair[$mode]['conflicts']);
        $pair[$mode]['conflict_examples'] = array_slice($pair[$mode]['conflicts'], 0, 3);
        unset($pair[$mode]['exclusive'], $pair[$mode]['conflicts']);
    }
    $pairs[$name] = $pair;
    echo $name.' : '.json_encode($pair['original']['summary']).PHP_EOL;
}
file_put_contents($directory.'/summary.json', json_encode(['generated_at' => now()->toIso8601String(), 'quality' => $quality, 'pairs' => $pairs], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
echo 'REPORT_DIRECTORY: '.$directory.PHP_EOL;
