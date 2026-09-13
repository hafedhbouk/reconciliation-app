<?php

use App\Jobs\ComputeUnmatchedJob;
use App\Models\Import;
use App\Models\NormalizedTransaction;
use App\Models\Source;
use App\Models\Transaction;
use App\Models\UnmatchedSnapshot;

test('file differences use the requested key in both directions regardless of matching status', function (string $codeA, string $codeB, string $key) {
    $importA = Import::factory()->create(['source_id' => Source::factory()->create(['code' => $codeA])->id]);
    $importB = Import::factory()->create(['source_id' => Source::factory()->create(['code' => $codeB])->id]);

    $make = function (Import $import, string $reference, string $authorization, string $date, int $amount, string $status = 'unmatched') {
        $transaction = Transaction::factory()->create([
            'source_id' => $import->source_id,
            'import_id' => $import->id,
            'raw_payload' => [
                in_array($import->source->code, ['WEB', 'STEG']) ? 'secondary_reference' : 'num_autorisation' => $authorization,
            ],
        ]);

        return NormalizedTransaction::factory()->create([
            'transaction_id' => $transaction->id,
            'normalized_reference' => $reference,
            'normalized_date' => $date,
            'normalized_amount_millimes' => $amount,
            'matching_status' => $status,
        ]);
    };

    $make($importA, '123456789', '001234', '2026-02-01', 16000, 'matched');
    $make($importB, $key === 'reference' ? '123456789' : '987654321',
        $key === 'authorization' ? '001234' : '009999',
        $key === 'date|amount' ? '2026-02-01' : '2026-02-02',
        $key === 'date|amount' ? 16000 : 99000);

    $onlyA = $make($importA, '111111111', '111111', '2026-03-01', 17000, 'matched');
    $onlyB = $make($importB, '222222222', '222222', '2026-03-02', 18000);
    // Neither equal dates alone nor equal amounts alone are sufficient.
    if ($key === 'date|amount') {
        $onlyB->update(['normalized_date' => '2026-03-01']);
        $extraB = $make($importB, '333333333', '333333', '2026-03-02', 17000);
    }

    foreach ([[$importA, $importB, [$onlyA->id], isset($extraB) ? [$onlyB->id, $extraB->id] : [$onlyB->id]],
        [$importB, $importA, isset($extraB) ? [$onlyB->id, $extraB->id] : [$onlyB->id], [$onlyA->id]]] as [$a, $b, $expectedA, $expectedB]) {
        $snapshot = UnmatchedSnapshot::create(['import_a_id' => $a->id, 'import_b_id' => $b->id, 'status' => 'pending']);
        (new ComputeUnmatchedJob($snapshot->id))->handle();
        $snapshot->refresh();
        expect($snapshot->status)->toBe('completed');
        expect(array_column($snapshot->result_a, 'id'))->toEqualCanonicalizing($expectedA);
        expect(array_column($snapshot->result_b, 'id'))->toEqualCanonicalizing($expectedB);
    }
})->with([
    ['ALPHA', 'BNA', 'authorization'],
    ['ALPHA', 'WEB', 'reference'],
    ['ALPHA', 'STEG', 'reference'],
    ['ALPHA', 'SMT', 'date|amount'],
    ['SMT', 'BNA', 'date|amount'],
    ['WEB', 'SMT', 'date|amount'],
    ['STEG', 'SMT', 'date|amount'],
    ['WEB', 'BNA', 'authorization'],
    ['STEG', 'BNA', 'authorization'],
]);

test('missing authorizations stay visible in both files', function () {
    $imports = collect(['ALPHA', 'BNA'])->map(fn ($code) => Import::factory()->create([
        'source_id' => Source::factory()->create(['code' => $code])->id,
    ]));
    foreach ($imports as $import) {
        NormalizedTransaction::factory()->create(['transaction_id' => Transaction::factory()->create([
            'source_id' => $import->source_id, 'import_id' => $import->id, 'raw_payload' => [],
        ])->id]);
    }
    $snapshot = UnmatchedSnapshot::create(['import_a_id' => $imports[0]->id, 'import_b_id' => $imports[1]->id, 'status' => 'pending']);
    (new ComputeUnmatchedJob($snapshot->id))->handle();
    $snapshot->refresh();
    expect($snapshot->status)->toBe('completed');
    expect($snapshot->result_a)->toHaveCount(1);
    expect($snapshot->result_b)->toHaveCount(1);
});
