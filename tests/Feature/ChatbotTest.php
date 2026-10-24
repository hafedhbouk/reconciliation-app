<?php

use App\Enums\ExceptionStatus;
use App\Enums\ExceptionType;
use App\Models\ExceptionRecord;
use App\Models\MatchingResult;
use App\Models\MatchingRule;
use App\Models\NormalizedTransaction;
use App\Models\Operation;
use App\Models\Source;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Chatbot\Chatbot;
use App\Services\Chatbot\QuestionParser;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-24 10:00:00', 'Africa/Tunis'));
});

afterEach(function () {
    Carbon::setTestNow();
});

/** Lignes importées : [date d'opération, statut]. */
function chatbotLines(string $code, array $rows): void
{
    $source = Source::query()->firstWhere('code', $code) ?? Source::factory()->create(['code' => $code]);
    foreach ($rows as [$date, $status]) {
        NormalizedTransaction::factory()->create([
            'transaction_id' => Transaction::factory()->create(['source_id' => $source->id])->id,
            'normalized_date' => $date, 'matching_status' => $status, 'normalized_amount_millimes' => 1000,
        ]);
    }
}

/** Juillet 2026 : ALPHA 3 lignes (2 rapprochées, 1 sans correspondance), BNA 2 lignes (1 rapprochée, 1 conflit). */
function chatbotDataset(): void
{
    chatbotLines('ALPHA', [['2026-07-01', 'matched'], ['2026-07-02', 'matched'], ['2026-07-03', 'unmatched']]);
    chatbotLines('BNA', [['2026-07-01', 'matched'], ['2026-07-02', 'conflict']]);
}

function ask(string $question): TestResponse
{
    return test()->postJson(route('chatbot.ask'), ['question' => $question])->assertOk();
}

test('the period and sources of a French question are understood', function (string $question, ?string $from, ?string $to, array $sources) {
    $parsed = app(QuestionParser::class)->parse($question, ['ALPHA', 'BNA', 'SMT']);

    expect($parsed->from?->format('Y-m-d'))->toBe($from)->and($parsed->to?->format('Y-m-d'))->toBe($to)->and($parsed->sources)->toBe($sources);
})->with([
    ['Taux de rapprochement du mois dernier ?', '2026-09-01', '2026-09-30', []],
    ['Combien de rapprochements cette année pour BNA ?', '2026-01-01', '2026-12-31', ['BNA']],
    ['Lignes sans correspondance en juillet', '2026-07-01', '2026-07-31', []],
    ['Conflits en décembre ?', '2025-12-01', '2025-12-31', []], // décembre à venir : celui de l'an dernier
    ['Taux ALPHA et smt en 2025', '2025-01-01', '2025-12-31', ['ALPHA', 'SMT']],
    ['Taux du 15/07/2026 au 01/07/2026', '2026-07-01', '2026-07-15', []],
    ['Imports d’aujourd’hui', '2026-10-24', '2026-10-24', []],
    ['Combien de lignes non rapprochées ?', null, null, []],
]);

test('the reconciliation rate is computed from the real lines', function () {
    actingAsAdmin();
    chatbotDataset();

    ask('Quel est le taux de rapprochement en juillet 2026 ?')
        ->assertJsonPath('statut', 'ok')->assertJsonPath('intention', 'taux_rapprochement')
        ->assertJsonPath('texte', 'En juillet 2026, le taux de rapprochement est de 60,0 % : 3 lignes rapprochées sur 5.')
        ->assertJsonPath('details.0', ['libelle' => 'ALPHA', 'valeur' => '66,7 % (2 / 3)'])
        ->assertJsonPath('details.1', ['libelle' => 'BNA', 'valeur' => '50,0 % (1 / 2)']);
    ask('Taux de rapprochement pour BNA ?')->assertJsonPath('texte', 'Sur l’ensemble des données pour BNA, le taux de rapprochement est de 50,0 % : 1 ligne rapprochée sur 2.');
});

test('a rate without any line is reported as not computable, never invented', function () {
    actingAsAdmin();
    chatbotDataset();

    ask('Quel est le taux de rapprochement du mois dernier ?')
        ->assertJsonPath('statut', 'non_calculable')
        ->assertJsonPath('texte', 'Aucune ligne importée le mois dernier (septembre 2026) : le taux de rapprochement ne peut pas être calculé. Les données disponibles vont du 01/07/2026 au 03/07/2026.')
        ->assertJsonPath('details', []);
});

test('unmatched lines and conflicts are counted per source', function () {
    actingAsAdmin();
    chatbotDataset();

    ask('Combien de lignes n’ont pas trouvé de correspondance ?')
        ->assertJsonPath('intention', 'lignes_sans_correspondance')
        ->assertJsonPath('texte', 'Sur l’ensemble des données, 1 ligne n’a pas trouvé de correspondance, sur 5 lignes importées (20,0 %). En plus, 1 ligne est en conflit (même clé, montant ou date différent).')
        ->assertJsonPath('details.0', ['libelle' => 'ALPHA', 'valeur' => '1 sur 3'])
        ->assertJsonPath('details.1', ['libelle' => 'BNA', 'valeur' => '0 sur 2']);
    ask('Combien de conflits pour BNA ?')->assertJsonPath('intention', 'conflits')
        ->assertJsonPath('texte', 'Sur l’ensemble des données pour BNA, 1 ligne est en conflit sur 2 lignes importées (taux d’anomalie : 50,0 %).');
});

test('reconciliations performed are counted by date and their monthly evolution is shown', function () {
    actingAsAdmin();
    $rule = MatchingRule::factory()->create();
    foreach ([['2026-08-10 09:00', 'matched'], ['2026-09-05 09:00', 'matched'], ['2026-09-06 09:00', 'partial'], ['2026-09-07 09:00', 'conflict'], ['2025-12-01 09:00', 'matched']] as [$at, $status]) {
        MatchingResult::factory()->create(['matching_rule_id' => $rule->id, 'status' => $status, 'batch_reference' => substr($at, 0, 7),
            'matched_at' => Carbon::parse($at, 'Africa/Tunis')->utc()]);
    }

    ask('Combien de rapprochements ont été effectués cette année ?')
        ->assertJsonPath('intention', 'rapprochements_effectues')
        ->assertJsonPath('texte', 'Cette année (2026), 3 rapprochements ont été effectués, en 2 exécutions.')
        ->assertJsonPath('details', [['libelle' => 'Rapproché', 'valeur' => '2'], ['libelle' => 'Partiel', 'valeur' => '1'],
            ['libelle' => 'Conflit', 'valeur' => '1'], ['libelle' => 'Exécutions (lots)', 'valeur' => '2']]);
    ask('Quelle est l’évolution du nombre de rapprochements par mois en 2026 ?')
        ->assertJsonPath('intention', 'evolution_mensuelle')
        ->assertJsonPath('details', [['libelle' => 'Août 2026', 'valeur' => '1'], ['libelle' => 'Septembre 2026', 'valeur' => '2']])
        ->assertJsonPath('texte', 'Rapprochements effectués par mois (en 2026) : 3 sur 2 mois, en hausse de 100,0 % entre août 2026 et septembre 2026.');
    ask('Combien de rapprochements effectués le mois dernier ?')->assertJsonPath('texte', 'Le mois dernier (septembre 2026), 2 rapprochements ont été effectués, en 1 exécution.');
    ask('Combien de rapprochements effectués en mars 2026 ?')->assertJsonPath('statut', 'vide')
        ->assertJsonPath('texte', 'Aucun rapprochement n’a été effectué en mars 2026.');
});

test('failed operations are limited to the user’s own operations unless administrator', function () {
    $user = actingAsPlainUser();
    $other = User::factory()->create(['name' => 'Autre agent']);
    foreach ([[$user, 'Import A.csv', 'Colonnes manquantes'], [$other, 'Import secret.csv', 'Erreur confidentielle']] as [$owner, $label, $error]) {
        Operation::create(['uuid' => (string) str()->uuid(), 'type' => 'import', 'label' => $label, 'user_id' => $owner->id,
            'status' => 'failed', 'error_message' => $error, 'finished_at' => now()]);
    }

    $answer = ask('Quels sont les derniers traitements échoués ?')->assertJsonPath('statut', 'ok')
        ->assertJsonPath('texte', 'Sur l’ensemble des données, 1 traitement a échoué (parmi vos opérations, sur 1 opération). Détail :');
    expect($answer->json('details.0.valeur'))->toBe('Colonnes manquantes')
        ->and(json_encode($answer->json()))->not->toContain('secret')->not->toContain('confidentielle')->not->toContain('Autre agent');

    actingAsAdmin();
    $all = ask('Quels sont les derniers traitements échoués ?')->assertJsonPath('texte', 'Sur l’ensemble des données, 2 traitements ont échoué (toutes opérations confondues, sur 2 opérations). Détail :');
    expect(collect($all->json('details'))->pluck('libelle')->implode(' '))->toContain('Autre agent');
});

test('questions on data the user may not see are refused', function () {
    actingAsPlainUser(); // aucun droit
    chatbotDataset();
    ExceptionRecord::factory()->create(['status' => ExceptionStatus::Open, 'type' => ExceptionType::Unmatched]);

    foreach (['Quel est le taux de rapprochement ?' => 'les résultats de rapprochement', 'Combien de rapprochements effectués ?' => 'les résultats de rapprochement',
        'Combien d’exceptions ouvertes ?' => 'les exceptions', 'Combien de fichiers importés ?' => 'les imports'] as $question => $subject) {
        ask($question)->assertJsonPath('statut', 'interdit')->assertJsonPath('details', [])
            ->assertJsonPath('texte', "Vous n’avez pas le droit de consulter {$subject} : je ne peux pas répondre à cette question.");
    }
    // Les exemples proposés ne contiennent que les questions autorisées.
    expect(ask('aide')->json('suggestions'))->toBe(['Quels sont les derniers traitements échoués ?']);
});

test('an operator can query the results and the exceptions of the role', function () {
    $this->seed(RolePermissionSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('operator'); // résultats, exceptions et imports : oui
    $this->actingAs($user);
    chatbotDataset();
    ExceptionRecord::factory()->create(['status' => ExceptionStatus::Open, 'type' => ExceptionType::Unmatched]);
    ExceptionRecord::factory()->create(['status' => ExceptionStatus::Resolved, 'type' => ExceptionType::Duplicate]);

    ask('Taux de rapprochement ?')->assertJsonPath('statut', 'ok');
    ask('Combien d’exceptions sont encore ouvertes ?')->assertJsonPath('statut', 'ok')
        ->assertJsonPath('texte', 'Sur l’ensemble des données, 2 exceptions : 1 encore à traiter (ouvertes ou en cours d’examen).');
});

test('unsupported questions are clearly reported', function () {
    actingAsAdmin();

    foreach (['Quel est le salaire du directeur ?', 'DROP TABLE users;', 'Raconte-moi une blague'] as $question) {
        ask($question)->assertJsonPath('statut', 'non_pris_en_charge')->assertJsonPath('details', [])
            ->assertJsonPath('texte', fn ($text) => str_starts_with($text, 'Je ne sais pas répondre à cette question'));
    }
    expect(ask('Bonjour')->json('suggestions'))->toHaveCount(8);
});

test('the endpoint requires a signed-in user and a valid question', function () {
    $this->postJson(route('chatbot.ask'), ['question' => 'Taux ?'])->assertUnauthorized();

    actingAsAdmin();
    $this->postJson(route('chatbot.ask'), ['question' => ''])->assertUnprocessable()->assertJsonPath('errors.question.0', 'Écrivez une question.');
    $this->postJson(route('chatbot.ask'), ['question' => str_repeat('a', 501)])->assertUnprocessable()
        ->assertJsonPath('errors.question.0', 'La question ne doit pas dépasser 500 caractères.');
});

test('the assistant window is available on the pages of signed-in users', function () {
    actingAsAdmin();

    $this->get(route('dashboard'))->assertOk()->assertSee('data-chatbot', false)->assertSee(route('chatbot.ask'), false)
        ->assertSee(e(json_encode(app(Chatbot::class)->examples(auth()->user()))), false);
    $this->get(route('login'))->assertDontSee('data-chatbot', false);
    expect(app(Chatbot::class)->examples(auth()->user()))->toHaveCount(8);
});
