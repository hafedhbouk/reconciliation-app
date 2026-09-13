<?php

use App\Models\Import;
use App\Models\UnmatchedSnapshot;
use App\Services\Matching\RuleMatcher;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$directory = $argv[1] ?? throw new InvalidArgumentException('Dossier du rapport requis.');
$report = json_decode(file_get_contents($directory.'/summary.json'), true, 512, JSON_THROW_ON_ERROR);
$checks = [];
foreach ($report['pairs'] as $name => $pair) {
    $a = Import::findOrFail($pair['imports'][0]['id']);
    $b = Import::findOrFail($pair['imports'][1]['id']);
    $existing = UnmatchedSnapshot::where('import_a_id', $a->id)->where('import_b_id', $b->id)->first();
    $checks[$name]['saved_difference_counts'] = $existing ? ['status' => $existing->status,
        'a' => count($existing->result_a ?? []), 'b' => count($existing->result_b ?? [])] : null;
    foreach ([false, true] as $reverse) {
        DB::beginTransaction();
        try {
            $summary = app(RuleMatcher::class)->refreshFileDifferences($reverse ? $b : $a, $reverse ? $a : $b);
            $expectedA = $pair['stored']['summary'][$reverse ? 'b' : 'a']['exclusive_rows'];
            $expectedB = $pair['stored']['summary'][$reverse ? 'a' : 'b']['exclusive_rows'];
            $ok = $summary->matched === $pair['stored']['exact_groups']
                && $summary->conflicts === $pair['stored']['conflict_groups']
                && $summary->unmatchedA === $expectedA && $summary->unmatchedB === $expectedB;
            $checks[$name][$reverse ? 'reverse' : 'forward'] = ['matches_independent_calculation' => $ok, 'summary' => (array) $summary];
            if (! $ok) {
                throw new RuntimeException('Écart moteur : '.$name);
            }
        } finally {
            DB::rollBack();
        }
    }
    echo $name.' : moteur vérifié dans les deux sens, écritures annulées'.PHP_EOL;
}
file_put_contents($directory.'/engine-checks.json', json_encode($checks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
