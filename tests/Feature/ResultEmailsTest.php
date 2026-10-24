<?php

use App\Jobs\RunAdHocMatchingJob;
use App\Mail\MatchingResultsMail;
use App\Models\Import;
use App\Models\MatchingExport;
use App\Models\NormalizedTransaction;
use App\Models\Operation;
use App\Models\ResultEmail;
use App\Models\Source;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Matching\RuleMatcher;
use App\Services\OperationTracker;
use App\Services\ResultEmails;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

/** Fichier importé terminé : une ligne par numéro d'autorisation (même montant, même date). */
function emailComparisonImport(string $code, array $authorizations): Import
{
    $source = Source::query()->firstWhere('code', $code) ?? Source::factory()->create(['code' => $code]);
    $import = Import::factory()->create(['source_id' => $source->id, 'status' => 'completed']);
    foreach ($authorizations as $authorization) {
        $transaction = Transaction::factory()->create([
            'import_id' => $import->id, 'source_id' => $source->id,
            'external_reference' => $code === 'BNA' ? null : '123456789',
            'raw_payload' => ['num_autorisation' => $authorization],
        ]);
        NormalizedTransaction::factory()->create([
            'transaction_id' => $transaction->id,
            'normalized_reference' => $code === 'BNA' ? $authorization : '123456789',
            'normalized_date' => '2026-07-01', 'normalized_amount_millimes' => 10000,
        ]);
    }

    return $import;
}

/** ALPHA {1, 2} ↔ BNA {1, 3, 4} : 1 rapprochement, 1 ligne seulement dans A, 2 seulement dans B. */
function emailComparisonFiles(): array
{
    return [emailComparisonImport('ALPHA', ['000001', '000002']), emailComparisonImport('BNA', ['000001', '000003', '000004'])];
}

function launchComparison(array $files, array $emailOptions): TestResponse
{
    return test()->post(route('admin.matching-rules.run-ad-hoc'), ['import_a_id' => $files[0]->id, 'import_b_id' => $files[1]->id] + $emailOptions);
}

/** Rapprochement terminé (comparaison de fichiers) lancé par $user. */
function completedComparison(User $user): Operation
{
    [$alpha, $bna] = emailComparisonFiles();
    $operation = app(OperationTracker::class)->create('matching', 'Rapprochement ALPHA ↔ BNA', $user->id);
    (new RunAdHocMatchingJob($alpha->id, $bna->id, 'email-batch', $user->id, $operation->id))->handle(app(RuleMatcher::class));

    return $operation->fresh();
}

function userWithRole(string $role, array $attributes = []): User
{
    return tap(User::factory()->create($attributes), fn (User $user) => $user->assignRole($role));
}

test('the results requested at launch are e-mailed with the real figures once the matching ends', function () {
    Mail::fake();
    $admin = actingAsAdmin();
    $director = userWithRole('directeur');

    launchComparison(emailComparisonFiles(), ['email_envoyer' => 1, 'email_destinataires' => [$admin->id, $director->id]])
        ->assertRedirect(route('admin.matching-rules.index'))
        ->assertSessionHas('status', fn ($status) => str_contains($status, 'envoyés par e-mail à 2 destinataires'));

    $operation = Operation::query()->where('type', 'matching')->sole();
    expect($operation->status)->toBe('completed')
        ->and(ResultEmail::query()->pluck('status')->all())->toBe(['sent', 'sent']);
    Mail::assertSentCount(2);
    Mail::assertSent(MatchingResultsMail::class, function (MatchingResultsMail $mail) use ($director, $operation) {
        $html = $mail->render();

        return $mail->hasTo($director->email)
            && $mail->summary['trouvees'] === 1 && $mail->summary['manquantes'] === 3 && $mail->summary['erreurs'] === 0
            && $mail->summary['traitees'] === 5 && $mail->summary['statut'] === 'Terminé'
            && $mail->file === null
            && str_contains($html, 'Correspondances manquantes') && str_contains($html, e($operation->label))
            && str_contains($html, e(rtrim(config('app.url'), '/').$operation->result_url))
            && ! str_contains($html, '000003'); // aucune ligne de données dans le message
    });
});

test('the results file is attached through the existing export, only for recipients with export access', function () {
    Mail::fake();
    $admin = actingAsAdmin();
    $reader = User::factory()->create(); // peut consulter les résultats, pas les exports
    $reader->givePermissionTo('matching-results.viewAny');

    launchComparison(emailComparisonFiles(), ['email_envoyer' => 1, 'email_piece_jointe' => 1, 'email_destinataires' => [$admin->id, $reader->id]]);

    $export = MatchingExport::query()->sole();
    expect($export->status)->toBe('completed')->and($export->format)->toBe('xlsx')
        ->and($export->filters['type'])->toBe('unmatched')->and($export->user_id)->toBe($admin->id);
    Mail::assertSent(MatchingResultsMail::class, fn (MatchingResultsMail $mail) => $mail->hasTo($admin->email)
        && str_ends_with($mail->file['name'], '.xlsx') && is_file($mail->file['path']) && count($mail->attachments()) === 1);
    Mail::assertSent(MatchingResultsMail::class, fn (MatchingResultsMail $mail) => $mail->hasTo($reader->email)
        && $mail->file === null && str_contains((string) $mail->note, 'n’a pas accès aux exports'));
    expect(ResultEmail::query()->where('email', $admin->email)->value('attachment_name'))->toEndWith('.xlsx');
});

test('only active accounts allowed to view the results can receive them', function () {
    Mail::fake();
    actingAsAdmin();
    $outsider = User::factory()->create(); // aucun droit
    $inactive = userWithRole('directeur', ['is_active' => false]);

    foreach ([$outsider, $inactive] as $recipient) {
        launchComparison(emailComparisonFiles(), ['email_envoyer' => 1, 'email_destinataires' => [$recipient->id]])
            ->assertSessionHasErrors(['email_destinataires.0' => 'Ce destinataire n’est pas autorisé à recevoir les résultats de rapprochement.']);
    }
    launchComparison(emailComparisonFiles(), ['email_envoyer' => 1])
        ->assertSessionHasErrors(['email_destinataires' => 'Choisissez au moins un destinataire pour l’envoi par e-mail.']);

    expect(ResultEmail::query()->count())->toBe(0)->and(Operation::query()->count())->toBe(0);
    Mail::assertNothingSent();
});

test('the same results are never sent twice to the same person', function () {
    Mail::fake();
    $admin = actingAsAdmin();
    $operation = completedComparison($admin);

    $this->post(route('admin.operations.email.store', $operation), ['email_destinataires' => [$admin->id]])->assertSessionHas('status');
    $this->post(route('admin.operations.email.store', $operation), ['email_destinataires' => [$admin->id]])
        ->assertSessionHas('error', fn ($error) => str_contains($error, 'Déjà envoyé'));
    app(ResultEmails::class)->deliver($operation->id); // job rejoué : rien n'est en file d'attente

    Mail::assertSentCount(1);
    expect(ResultEmail::query()->sole())->status->toBe('sent')->attempts->toBe(1);
});

test('a sending failure is traced, keeps the matching successful and can be retried', function () {
    $admin = actingAsAdmin();
    $operation = completedComparison($admin);
    // Vrai serveur SMTP injoignable (port fermé) : la connexion est refusée.
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 2]);

    $this->post(route('admin.operations.email.store', $operation), ['email_destinataires' => [$admin->id]]);

    $email = ResultEmail::query()->sole();
    expect($email->status)->toBe('failed')->and($email->error_message)->toContain('Connection could not be established')
        ->and($operation->fresh()->status)->toBe('completed');
    $this->get(route('admin.operations.email', $operation))->assertOk()
        ->assertSee('Échec de l’envoi')->assertSee('Connection could not be established')->assertSee('Renvoyer');

    Mail::fake(); // le serveur répond de nouveau
    $this->post(route('admin.result-emails.retry', $email))->assertSessionHas('status');
    expect($email->fresh())->status->toBe('sent')->attempts->toBe(2);
    Mail::assertSent(MatchingResultsMail::class, 1);
    $this->post(route('admin.result-emails.retry', $email))->assertSessionHas('error'); // déjà envoyé : pas de nouvel envoi
    Mail::assertSent(MatchingResultsMail::class, 1);
});

test('nothing is sent when the matching fails', function () {
    Mail::fake();
    $admin = actingAsAdmin();
    [$alpha, $bna] = emailComparisonFiles();
    $operation = app(OperationTracker::class)->create('matching', 'Rapprochement', $admin->id);
    app(ResultEmails::class)->request($operation, $admin, [$admin->id], false);
    expect(ResultEmail::query()->sole()->status)->toBe('waiting');

    $bna->delete(); // fichier supprimé avant le traitement : le rapprochement échoue
    expect(fn () => (new RunAdHocMatchingJob($alpha->id, $bna->id, 'failed-batch', $admin->id, $operation->id))->handle(app(RuleMatcher::class)))
        ->toThrow(InvalidArgumentException::class);

    expect($operation->fresh()->status)->toBe('failed')
        ->and(ResultEmail::query()->sole())->status->toBe('cancelled')->note->toContain('Rapprochement en échec');
    Mail::assertNothingSent();
});

test('a recipient deactivated before the end of the matching does not receive the results', function () {
    Mail::fake();
    $admin = actingAsAdmin();
    $director = userWithRole('directeur');
    $operation = app(OperationTracker::class)->create('matching', 'Rapprochement', $admin->id);
    app(ResultEmails::class)->request($operation, $admin, [$director->id], false);
    $director->update(['is_active' => false]);

    $operation->update(['status' => 'completed', 'context' => ['kind' => 'comparison', 'import_a_id' => 0, 'import_b_id' => 0], 'finished_at' => now()]);
    app(ResultEmails::class)->operationCompleted($operation->id);

    expect(ResultEmail::query()->sole())->status->toBe('failed')->error_message->toContain('Destinataire désactivé');
    Mail::assertNothingSent();
});

test('a rule run reports the lines still unmatched as missing matches', function () {
    $operation = new Operation(['type' => 'matching', 'label' => 'Règle « Référence »', 'status' => 'completed', 'processed' => 12,
        'summary' => ['references' => 12, 'rapprochements' => 10, 'conflits' => 2, 'non_rapprochees' => 7],
        'context' => ['kind' => 'rule', 'matching_rule_id' => 1, 'batch_reference' => 'x'], 'result_url' => '/admin/matching-results']);

    expect(app(ResultEmails::class)->summary($operation))
        ->unite->toBe('clés')->traitees->toBe(12)->trouvees->toBe(10)->manquantes->toBe(7)->erreurs->toBe(2);
});

test('only the owner of a finished matching, or an administrator, can send its results', function () {
    Mail::fake();
    $this->seed(RolePermissionSeeder::class);
    $owner = userWithRole('agent-execution');
    $operation = completedComparison($owner);
    $failed = app(OperationTracker::class)->create('matching', 'Rapprochement en échec', $owner->id);
    $failed->update(['status' => 'failed']);

    $this->actingAs($owner)->get(route('admin.operations.email', $operation))->assertOk()
        ->assertSee('Correspondances trouvées')->assertSee('Historique des envois');
    $this->get(route('admin.operations.email', $failed))->assertForbidden();
    expect(collect($this->getJson(route('admin.operations.data'))->json('operations'))->pluck('email_url', 'label')->all())
        ->toBe(['Rapprochement en échec' => null, 'Rapprochement ALPHA ↔ BNA' => route('admin.operations.email', $operation, false)]);

    $colleague = userWithRole('agent-execution');
    $this->actingAs($colleague)->get(route('admin.operations.email', $operation))->assertForbidden();
    $this->post(route('admin.operations.email.store', $operation), ['email_destinataires' => [$colleague->id]])->assertForbidden();

    $this->actingAs($owner)->post(route('admin.operations.email.store', $operation), ['email_destinataires' => [$owner->id]]);
    $this->actingAs($colleague)->post(route('admin.result-emails.retry', ResultEmail::query()->sole()))->assertForbidden();

    $this->actingAs(userWithRole('admin'))->get(route('admin.operations.email', $operation))->assertOk();
});

test('the comparison form offers the e-mail option with the authorized recipients only', function () {
    $admin = actingAsAdmin();
    $director = userWithRole('directeur');
    $outsider = User::factory()->create();

    $this->get(route('admin.matching-rules.index'))->assertOk()
        ->assertSee('Envoyer les résultats par e-mail à la fin du rapprochement')
        ->assertSee($director->email)->assertSee($admin->email)->assertDontSee($outsider->email)
        ->assertSee('Joindre le fichier des résultats');
});

test('a lot replayed with its stored result still reports the lines of both files', function () {
    $user = User::factory()->create();
    [$alpha, $bna] = emailComparisonFiles();
    foreach ([1, 2] as $run) {
        $operation = app(OperationTracker::class)->create('matching', "Passage {$run}", $user->id);
        (new RunAdHocMatchingJob($alpha->id, $bna->id, 'same-batch', null, $operation->id))->handle(app(RuleMatcher::class));
        expect($operation->fresh())->status->toBe('completed')->processed->toBe(5)
            ->summary->toBe(['rapprochements' => 1, 'conflits' => 0, 'exclusives_a' => 1, 'exclusives_b' => 2]);
    }
});

test('the e-mail is written in French whatever the language of the worker', function () {
    app()->setLocale('en');
    $html = (new MatchingResultsMail(['operation' => 'Test', 'date' => '01/07/2026 à 10:00:00', 'statut' => 'Terminé', 'traitees' => 1,
        'unite' => 'lignes', 'trouvees' => 1, 'manquantes' => 0, 'manquantes_detail' => [], 'erreurs' => 0, 'lien' => null]))->render();

    expect($html)->toContain('Tous droits réservés')->not->toContain('All rights reserved');
});
