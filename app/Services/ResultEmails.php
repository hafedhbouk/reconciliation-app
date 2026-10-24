<?php

namespace App\Services;

use App\Jobs\GenerateMatchingExportJob;
use App\Jobs\SendResultEmailsJob;
use App\Mail\MatchingResultsMail;
use App\Models\MatchingExport;
use App\Models\MatchingResult;
use App\Models\Operation;
use App\Models\ResultEmail;
use App\Models\UnmatchedSnapshot;
use App\Models\User;
use App\Services\Matching\SnapshotRows;
use App\Support\DateDisplay;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Envoi des résultats d'un rapprochement par e-mail (Phase 4).
 *
 * Déroulement :
 *   1. l'utilisateur demande l'envoi, au lancement (« Comparer deux fichiers »)
 *      ou après coup (bouton « E-mail » de « Mes opérations ») : request() crée
 *      une ligne result_emails par destinataire ;
 *   2. si le rapprochement n'est pas fini, les lignes attendent (« waiting ») :
 *      le job de rapprochement appelle operationCompleted() ou operationFailed() ;
 *   3. SendResultEmailsJob appelle deliver(), qui envoie un e-mail par
 *      destinataire et note le résultat (envoyé, ou échec avec le message).
 *
 * Garanties :
 *   - un échec d'envoi ne change jamais le statut du rapprochement : l'envoi
 *     est un job séparé, lancé après la fin du rapprochement, et toutes ses
 *     erreurs sont attrapées et notées sur la ligne d'envoi ;
 *   - pas d'envoi répété : une ligne par (rapprochement, adresse), contrainte
 *     d'unicité en base, et chaque ligne est « réservée » (queued → sending)
 *     par une seule mise à jour avant l'envoi ;
 *   - destinataires autorisés : comptes actifs ayant le droit de consulter les
 *     résultats, vérifiés à la demande ET au moment de l'envoi ;
 *   - le corps de l'e-mail ne contient que des chiffres : le détail des lignes
 *     n'est envoyé qu'en pièce jointe, sur demande, et seulement aux
 *     destinataires qui ont accès aux exports dans l'application.
 */
class ResultEmails
{
    /** Droit nécessaire pour recevoir les résultats. */
    public const PERMISSION = 'matching-results.viewAny';

    /** Droit nécessaire pour demander ou recevoir le fichier joint (le contenu d'un export). */
    public const ATTACHMENT_PERMISSION = 'matching-exports.viewAny';

    /** Au-delà, le fichier n'est pas joint (il reste téléchargeable dans l'application). */
    public const MAX_ATTACHMENT_BYTES = 10 * 1024 * 1024;

    /** Même limite que les exports Excel existants : au-delà, le fichier est en CSV. */
    public const XLSX_MAX_ROWS = 1000;

    /** Comptes actifs pouvant consulter les résultats de rapprochement. */
    public function authorizedRecipients(): Collection
    {
        return User::query()->where('is_active', true)->whereNotNull('email')->orderBy('name')->get()
            ->filter(fn (User $user) => $this->isAuthorized($user))->values();
    }

    public function isAuthorized(?User $user): bool
    {
        return $user !== null && $user->is_active && $user->email !== null && $user->can(self::PERMISSION);
    }

    /** L'utilisateur peut-il demander la pièce jointe ? */
    public function canAttach(?User $user): bool
    {
        return $user !== null && $user->can(self::ATTACHMENT_PERMISSION);
    }

    /** Rapprochement terminé dont on sait retrouver les résultats. */
    public function isSendable(Operation $operation): bool
    {
        return $operation->type === 'matching' && $operation->status === 'completed' && ! empty($operation->context);
    }

    /** Bouton « E-mail » de « Mes opérations » : rapprochement terminé, visible et consultable par l'utilisateur. */
    public function canRequest(?User $user, Operation $operation): bool
    {
        return $user !== null && $this->isSendable($operation) && $user->can('view', $operation) && $user->can(self::PERMISSION);
    }

    /**
     * Demande d'envoi. Les destinataires non autorisés sont ignorés ; un
     * destinataire à qui ces résultats ont déjà été envoyés (ou vont l'être)
     * n'est pas ajouté une seconde fois. Un envoi en échec ou annulé est relancé.
     *
     * @param  iterable<int>  $recipientIds
     * @return array{added: list<string>, already: list<string>}
     */
    public function request(Operation $operation, User $requester, iterable $recipientIds, bool $withAttachment): array
    {
        $status = match ($operation->status) {
            'completed' => 'queued',
            'pending', 'running' => 'waiting',
            default => throw new \InvalidArgumentException('Ce rapprochement a échoué : il n’y a pas de résultats à envoyer.'),
        };
        $ids = collect($recipientIds)->map(fn ($id) => (int) $id)->unique();
        $recipients = $this->authorizedRecipients()->whereIn('id', $ids->all());
        $withAttachment = $withAttachment && $this->canAttach($requester);
        $added = $already = [];

        foreach ($recipients as $recipient) {
            $existing = ResultEmail::query()->where('operation_id', $operation->id)->where('email', $recipient->email)->first();
            if ($existing !== null && ! in_array($existing->status, ['failed', 'cancelled'], true)) {
                $already[] = $recipient->email;

                continue;
            }
            $attributes = ['recipient_id' => $recipient->id, 'requested_by' => $requester->id, 'with_attachment' => $withAttachment,
                'status' => $status, 'error_message' => null, 'note' => null];
            try {
                $existing !== null
                    ? $existing->update($attributes)
                    : ResultEmail::query()->create($attributes + ['operation_id' => $operation->id, 'email' => $recipient->email]);
                $added[] = $recipient->email;
            } catch (UniqueConstraintViolationException) {
                $already[] = $recipient->email; // demande simultanée (double clic) : déjà enregistrée
            }
        }

        if ($status === 'queued' && $added !== []) {
            SendResultEmailsJob::dispatch($operation->id);
        }

        return ['added' => $added, 'already' => $already];
    }

    /** Relance d'un envoi en échec (bouton « Renvoyer »). */
    public function retry(ResultEmail $email): bool
    {
        $updated = ResultEmail::query()->whereKey($email->id)->where('status', 'failed')
            ->update(['status' => 'queued', 'error_message' => null, 'updated_at' => now()]);
        if ($updated === 1) {
            SendResultEmailsJob::dispatch($email->operation_id);
        }

        return $updated === 1;
    }

    /** Fin réussie d'un rapprochement : les envois en attente partent. Ne lève jamais d'exception. */
    public function operationCompleted(?int $operationId): void
    {
        if ($operationId === null) {
            return;
        }
        try {
            $queued = ResultEmail::query()->where('operation_id', $operationId)->where('status', 'waiting')
                ->update(['status' => 'queued', 'updated_at' => now()]);
            if ($queued > 0) {
                SendResultEmailsJob::dispatch($operationId);
            }
        } catch (Throwable $e) {
            // Le rapprochement reste réussi ; l'envoi pourra être redemandé depuis « Mes opérations ».
            Log::error('Préparation des e-mails de résultats impossible', ['operation_id' => $operationId, 'error' => $e->getMessage()]);
        }
    }

    /** Échec du rapprochement : les envois en attente sont annulés (rien à envoyer). Ne lève jamais d'exception. */
    public function operationFailed(?int $operationId): void
    {
        if ($operationId === null) {
            return;
        }
        try {
            ResultEmail::query()->where('operation_id', $operationId)->where('status', 'waiting')
                ->update(['status' => 'cancelled', 'note' => 'Rapprochement en échec : aucun résultat à envoyer.', 'updated_at' => now()]);
        } catch (Throwable $e) {
            Log::error('Annulation des e-mails de résultats impossible', ['operation_id' => $operationId, 'error' => $e->getMessage()]);
        }
    }

    /** Envoi des e-mails « En file d'attente » d'un rapprochement (SendResultEmailsJob). */
    public function deliver(int $operationId): void
    {
        $operation = Operation::query()->find($operationId);
        $pending = ResultEmail::query()->with(['recipient', 'requester'])->where('operation_id', $operationId)->where('status', 'queued')->get();
        // Réservation : seule la mise à jour qui passe la ligne de queued à sending l'envoie (pas de double envoi).
        $claimed = $pending->filter(fn (ResultEmail $email) => ResultEmail::query()->whereKey($email->id)->where('status', 'queued')
            ->update(['status' => 'sending', 'attempts' => $email->attempts + 1, 'updated_at' => now()]) === 1)->values();
        if ($claimed->isEmpty()) {
            return;
        }
        if ($operation === null || ! $this->isSendable($operation)) {
            $this->markAll($claimed, 'cancelled', 'Les résultats de ce rapprochement ne sont plus disponibles.');

            return;
        }

        $summary = $this->summary($operation);
        [$attachment, $attachmentNote] = $claimed->contains('with_attachment', true)
            ? $this->attachment($operation, $claimed->firstWhere('with_attachment', true)->requester ?? $operation->user)
            : [null, null];

        foreach ($claimed as $email) {
            if (! $this->isAuthorized($email->recipient)) {
                $email->update(['status' => 'failed', 'error_message' => 'Destinataire désactivé ou sans droit de consulter les résultats.']);

                continue;
            }
            $attach = $email->with_attachment && $attachment !== null && $this->canAttach($email->recipient);
            $note = ! $email->with_attachment ? null
                : ($attachmentNote ?? ($attach ? null : 'Pièce jointe non envoyée : le destinataire n’a pas accès aux exports.'));
            try {
                Mail::to($email->email, $email->recipient->name)->send(new MatchingResultsMail($summary, $attach ? $attachment : null, $note));
                $email->update(['status' => 'sent', 'sent_at' => now(), 'error_message' => null, 'note' => $note,
                    'attachment_name' => $attach ? $attachment['name'] : null]);
            } catch (Throwable $e) {
                Log::warning('Échec de l’envoi des résultats par e-mail', ['result_email_id' => $email->id, 'error' => $e->getMessage()]);
                $email->update(['status' => 'failed', 'error_message' => Str::limit($e->getMessage(), 1000), 'note' => $note]);
            }
        }
    }

    /**
     * Chiffres du rapprochement pour l'e-mail, relus sur l'opération terminée
     * (les mêmes que dans « Mes opérations »).
     *
     * @return array<string,mixed>
     */
    public function summary(Operation $operation): array
    {
        $results = $operation->summary ?? [];
        $comparison = ($operation->context['kind'] ?? null) === 'comparison';

        return [
            'operation' => $operation->label,
            'date' => DateDisplay::dateTime($operation->finished_at),
            'statut' => Operation::STATUSES[$operation->status] ?? $operation->status,
            'traitees' => (int) $operation->processed,
            'unite' => $comparison ? 'lignes' : 'clés',
            'trouvees' => (int) ($results['rapprochements'] ?? 0),
            // Comparaison de fichiers : lignes présentes d'un seul côté. Règle : lignes encore non rapprochées des deux sources.
            'manquantes' => $comparison
                ? (int) ($results['exclusives_a'] ?? 0) + (int) ($results['exclusives_b'] ?? 0)
                : (isset($results['non_rapprochees']) ? (int) $results['non_rapprochees'] : null),
            'manquantes_detail' => $comparison
                ? ['Fichier A' => (int) ($results['exclusives_a'] ?? 0), 'Fichier B' => (int) ($results['exclusives_b'] ?? 0)]
                : [],
            'erreurs' => (int) ($results['conflits'] ?? 0),
            // Lien vers la page de l'application : il faut se connecter pour voir les résultats.
            'lien' => $operation->result_url !== null ? rtrim((string) config('app.url'), '/').$operation->result_url : null,
        ];
    }

    /**
     * Fichier des résultats, produit par l'export existant (GenerateMatchingExportJob) :
     * écarts entre fichiers pour une comparaison, résultats du lot pour une règle.
     * Il est aussi visible dans la page « Exports » de l'utilisateur.
     *
     * @return array{0: array{path:string,name:string}|null, 1: string|null} [fichier, remarque]
     */
    private function attachment(Operation $operation, ?User $requester): array
    {
        try {
            $context = $operation->context;
            if (($context['kind'] ?? null) === 'comparison') {
                $snapshot = UnmatchedSnapshot::query()->where('import_a_id', $context['import_a_id'])
                    ->where('import_b_id', $context['import_b_id'])->firstOrFail();
                app(SnapshotRows::class)->ensureStored($snapshot);
                $rows = DB::table('unmatched_snapshot_rows')->where('snapshot_id', $snapshot->id)->count();
                $filters = ['type' => 'unmatched', 'snapshot_id' => $snapshot->id];
                $name = 'ecarts';
            } else {
                $filters = array_filter(['batch_reference' => $context['batch_reference'] ?? null, 'matching_rule_id' => $context['matching_rule_id'] ?? null]);
                $rows = MatchingResult::query()->where($filters)->count();
                $name = 'resultats';
            }
            $format = $rows <= self::XLSX_MAX_ROWS ? 'xlsx' : 'csv';
            $export = MatchingExport::query()->create(['user_id' => $requester?->id ?? $operation->user_id, 'format' => $format,
                'status' => 'pending', 'filters' => $filters, 'download_token' => Str::random(64)]);
            (new GenerateMatchingExportJob($export))->handle();
            $path = Storage::path((string) $export->fresh()->file_path);
            $size = is_file($path) ? filesize($path) : false;
            if ($size === false) {
                return [null, 'La pièce jointe n’a pas pu être générée.'];
            }
            if ($size > self::MAX_ATTACHMENT_BYTES) {
                return [null, sprintf('Fichier trop volumineux pour être joint (%s Mo) : il est disponible dans la page « Exports » de l’application.',
                    number_format($size / 1048576, 1, ',', ' '))];
            }

            return [['path' => $path, 'name' => "{$name}-rapprochement-{$operation->id}.{$format}"], null];
        } catch (Throwable $e) {
            Log::warning('Pièce jointe des résultats impossible', ['operation_id' => $operation->id, 'error' => $e->getMessage()]);

            return [null, 'La pièce jointe n’a pas pu être générée.'];
        }
    }

    private function markAll(Collection $emails, string $status, string $note): void
    {
        ResultEmail::query()->whereIn('id', $emails->pluck('id'))->update(['status' => $status, 'note' => $note, 'updated_at' => now()]);
    }
}
