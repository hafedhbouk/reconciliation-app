<?php

use App\Models\Import;
use App\Models\NormalizedTransaction;
use App\Models\Operation;
use App\Models\Source;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Analytics\ReconciliationAnalytics;
use Illuminate\Testing\TestResponse;

/** Lignes importées d'une source : [date, statut, montant en millimes, canal]. */
function analyticsRows(string $code, array $rows, ?Import $import = null): void
{
    $source = Source::query()->firstWhere('code', $code) ?? Source::factory()->create(['code' => $code]);
    foreach ($rows as [$date, $status, $amount, $canal]) {
        $transaction = Transaction::factory()->create(['source_id' => $source->id, 'import_id' => $import?->id, 'canal' => $canal]);
        NormalizedTransaction::factory()->create([
            'transaction_id' => $transaction->id, 'normalized_date' => $date,
            'matching_status' => $status, 'normalized_amount_millimes' => $amount,
        ]);
    }
}

/** Jeu de données : 10 lignes ALPHA et BNA sur juillet 2026. */
function analyticsDataset(): void
{
    analyticsRows('ALPHA', [
        ['2026-07-01', 'matched', 1000, 'WEB'], ['2026-07-01', 'matched', 2000, 'WEB'],
        ['2026-07-02', 'matched', 3000, 'TT_USSD'], ['2026-07-02', 'unmatched', 4000, 'TT_USSD'],
        ['2026-07-03', 'conflict', 5000, 'WEB'], ['2026-07-03', 'matched', 6000, 'WEB'],
    ]);
    analyticsRows('BNA', [
        ['2026-07-01', 'matched', 1000, null], ['2026-07-02', 'matched', 3000, null],
        ['2026-07-03', 'unmatched', 7000, null], ['2026-07-20', 'partial', 9000, null],
    ]);
}

function analytics(array $query = []): TestResponse
{
    return test()->getJson(route('dashboard.analytics', $query))->assertOk();
}

test('indicators are computed from the real lines and statuses', function () {
    actingAsAdmin();
    analyticsDataset();

    analytics()->assertJsonPath('rapprochement.indicateurs.lignes', 10)
        ->assertJsonPath('rapprochement.indicateurs.montant_millimes', 41000)
        ->assertJsonPath('rapprochement.indicateurs.rapprochees', 7) // rapprochées + partielles
        ->assertJsonPath('rapprochement.indicateurs.non_rapprochees', 2)
        ->assertJsonPath('rapprochement.indicateurs.conflits', 1)
        ->assertJsonPath('rapprochement.indicateurs.taux_rapprochement', 70)
        ->assertJsonPath('rapprochement.indicateurs.taux_anomalie', 10);
});

test('filters combine source, period and status', function () {
    actingAsAdmin();
    analyticsDataset();

    analytics(['sources' => ['ALPHA'], 'date_debut' => '2026-07-02', 'date_fin' => '2026-07-03'])
        ->assertJsonPath('rapprochement.indicateurs.lignes', 4)
        ->assertJsonPath('rapprochement.indicateurs.taux_rapprochement', 50);
    analytics(['sources' => ['ALPHA', 'BNA'], 'statuts' => ['unmatched']])
        ->assertJsonPath('rapprochement.indicateurs.lignes', 2)
        ->assertJsonPath('rapprochement.indicateurs.montant_millimes', 11000);
    analytics(['annee' => 2025])->assertJsonPath('rapprochement.indicateurs.lignes', 0);
});

test('the pivot table groups, totals and computes percentages on the right total', function () {
    actingAsAdmin();
    analyticsDataset();

    $pivot = analytics(['dim_lignes' => 'source', 'dim_colonnes' => 'statut'])->json('rapprochement.croise');

    expect(array_column($pivot['lignes'], 'libelle'))->toBe(['ALPHA', 'BNA'])
        ->and(array_column($pivot['colonnes'], 'libelle'))->toBe(['Non rapproché', 'Rapproché', 'Partiel', 'Conflit'])
        // ALPHA : 1 non rapprochée, 4 rapprochées, 0 partielle, 1 conflit.
        ->and(array_column($pivot['cellules'][0], 'valeur'))->toBe([1, 4, 0, 1])
        ->and($pivot['cellules'][0][1]['pct_total'])->toEqual(40.0)    // 4 / 10 lignes
        ->and($pivot['cellules'][0][1]['pct_ligne'])->toEqual(66.7) // 4 / 6 lignes ALPHA
        ->and(array_column($pivot['totaux_lignes'], 'valeur'))->toBe([6, 4])
        ->and(array_column($pivot['totaux_colonnes'], 'valeur'))->toBe([2, 6, 1, 1])
        ->and($pivot['total'])->toBe(10);

    $amounts = analytics(['dim_lignes' => 'canal', 'dim_colonnes' => 'aucune', 'mesure' => 'montant'])->json('rapprochement.croise');
    expect(array_column($amounts['lignes'], 'libelle'))->toBe(['TT_USSD', 'WEB', '—'])
        ->and(array_column(array_merge(...$amounts['cellules']), 'valeur'))->toBe([7000, 14000, 20000])
        ->and($amounts['total'])->toBe(41000);
});

test('a very small rate keeps enough decimals not to show 0 %', function () {
    expect(ReconciliationAnalytics::percent(9, 318890))->toBe(0.003)
        ->and(ReconciliationAnalytics::percent(0, 318890))->toEqual(0.0)
        ->and(ReconciliationAnalytics::percent(4, 6))->toBe(66.7);
});

test('percentages are null instead of dividing by zero', function () {
    actingAsAdmin();
    analyticsDataset();

    $response = analytics(['annee' => 2025]);
    expect($response->json('rapprochement.indicateurs.taux_rapprochement'))->toBeNull()
        ->and($response->json('rapprochement.croise.lignes'))->toBe([])
        ->and($response->json('rapprochement.croise.total'))->toBe(0);
});

test('the timeline is daily over a short period and monthly over a long one', function () {
    actingAsAdmin();
    analyticsDataset();

    $daily = analytics()->json('rapprochement.evolution');
    expect($daily['granularite'])->toBe('jour')
        ->and($daily['periodes'])->toBe(['01/07/2026', '02/07/2026', '03/07/2026', '20/07/2026'])
        ->and(collect($daily['series'])->firstWhere('statut', 'matched')['valeurs'])->toBe([3, 2, 1, 0]);

    analyticsRows('BNA', [['2026-12-15', 'matched', 500, null]]);
    $monthly = analytics()->json('rapprochement.evolution');
    expect($monthly['granularite'])->toBe('mois')
        ->and($monthly['periodes'])->toBe(['juillet 2026', 'décembre 2026']);
});

test('figures follow the data as soon as a line is reconciled', function () {
    actingAsAdmin();
    analyticsDataset();
    analytics()->assertJsonPath('rapprochement.indicateurs.non_rapprochees', 2);

    NormalizedTransaction::query()->where('matching_status', 'unmatched')->first()->update(['matching_status' => 'matched']);

    analytics()->assertJsonPath('rapprochement.indicateurs.non_rapprochees', 1);
});

test('users without the matching permission only get their own operations', function () {
    $user = actingAsPlainUser();
    analyticsDataset();
    $other = User::factory()->create();
    foreach ([[$user, 'import', 'completed'], [$user, 'export', 'failed'], [$other, 'matching', 'completed']] as [$owner, $type, $status]) {
        Operation::create(['uuid' => (string) str()->uuid(), 'type' => $type, 'label' => "{$type} test", 'user_id' => $owner->id,
            'status' => $status, 'error_message' => $status === 'failed' ? 'Disque plein' : null, 'finished_at' => now()]);
    }

    analytics(['utilisateur' => $other->id]) // ignoré : pas administrateur
        ->assertJsonPath('rapprochement', null)
        ->assertJsonPath('operations.total', 2)
        ->assertJsonPath('operations.echecs', 1)
        ->assertJsonPath('operations.taux_succes', 50)
        ->assertJsonPath('operations.derniers_echecs.0.erreur', 'Disque plein');

    actingAsAdmin();
    analytics()->assertJsonPath('operations.total', 3);
    analytics(['utilisateur' => $other->id, 'types' => ['matching']])->assertJsonPath('operations.total', 1);
});

test('invalid filters are rejected with French messages', function () {
    actingAsAdmin();

    $this->getJson(route('dashboard.analytics', ['dim_lignes' => 'password']))
        ->assertUnprocessable()->assertJsonPath('errors.dim_lignes.0', 'La valeur sélectionnée pour regroupement des lignes n’est pas valide.');
    $this->getJson(route('dashboard.analytics', ['dim_lignes' => 'source', 'dim_colonnes' => 'source']))
        ->assertUnprocessable()->assertJsonPath('errors.dim_colonnes.0', 'Choisissez deux regroupements différents pour les lignes et les colonnes.');
    $this->getJson(route('dashboard.analytics', ['date_debut' => '2026-07-10', 'date_fin' => '2026-07-01']))
        ->assertUnprocessable()->assertJsonPath('errors.date_fin.0', 'La date de fin doit être postérieure ou égale à la date de début.');
});

test('the dashboard shows the analytics only with the matching permission', function () {
    actingAsAdmin();
    $this->get(route('dashboard'))->assertOk()
        ->assertSee('Analyse du rapprochement')->assertSee('Tableau croisé dynamique')->assertSee('data-analytics-status', false);

    actingAsPlainUser();
    $this->get(route('dashboard'))->assertOk()
        ->assertDontSee('Analyse du rapprochement')->assertSee('Mes opérations')->assertSee(__('Volume par source'));
});
