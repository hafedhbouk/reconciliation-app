<?php

namespace App\Services;

use App\Models\Operation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Enregistre l'avancement réel des opérations longues (table operations).
 *
 * Cycle de vie : create() au clic de l'utilisateur (« En attente »), puis,
 * dans le job : start() → advance()… → finish() ou fail().
 *
 * Pourquoi une connexion de base séparée ? Le rapprochement et la comparaison
 * de fichiers tournent dans UNE grande transaction SQL : écrit sur la même
 * connexion, l'avancement resterait invisible pour les autres pages jusqu'à
 * la fin. La connexion « operations_tracking » (copie de la connexion
 * principale) valide chaque mise à jour immédiatement. Avec SQLite (tests,
 * base en mémoire), une seconde connexion serait une autre base vide : on
 * garde alors la connexion principale.
 *
 * advance() n'écrit qu'environ une fois par seconde (THROTTLE_SECONDS) pour
 * ne pas ralentir les traitements de 80 000 lignes.
 *
 * Enregistré comme singleton (AppServiceProvider) : le délai entre deux
 * écritures est suivi par opération pendant toute la durée du job.
 */
class OperationTracker
{
    public const CONNECTION = 'operations_tracking';

    public const THROTTLE_SECONDS = 1.0;

    /** @var array<int,float> heure (microtime) de la dernière écriture, par id d'opération */
    private array $lastWrite = [];

    /** Crée une opération « En attente », visible tout de suite dans « Mes opérations ». */
    public function create(string $type, string $label, ?int $userId, ?Model $subject = null): Operation
    {
        return Operation::on($this->connection())->create([
            'uuid' => (string) Str::uuid(),
            'type' => $type,
            'label' => Str::limit($label, 250),
            'user_id' => $userId,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'status' => 'pending',
            'processed' => 0,
        ]);
    }

    /**
     * Opération d'un job : celle créée au lancement (id transmis au job), sinon
     * une nouvelle (job lancé sans écran : chaîne « Lancer tout », commande, test).
     */
    public function resolve(?int $operationId, string $type, string $label, ?int $userId, ?Model $subject = null): Operation
    {
        $operation = $operationId !== null ? Operation::on($this->connection())->find($operationId) : null;

        return $operation ?? $this->create($type, $label, $userId, $subject);
    }

    /** Le job commence : « En cours ». $total = nombre d'éléments à traiter, null s'il est inconnu. */
    public function start(Operation $operation, ?int $total = null, ?string $stage = null): void
    {
        $this->write($operation, [
            'status' => 'running',
            'started_at' => $operation->started_at ?? now(),
            'finished_at' => null,
            'error_message' => null,
            'stage' => $stage,
            'indeterminate' => false,
            'total' => $total,
        ]);
    }

    /**
     * Change d'étape. $indeterminate = étape dont l'avancement n'est pas mesurable
     * (aucun pourcentage n'est alors affiché, seulement « en cours »).
     */
    public function stage(Operation $operation, string $stage, bool $indeterminate = false, ?int $total = null): void
    {
        $this->write($operation, array_filter([
            'stage' => $stage,
            'indeterminate' => $indeterminate,
            'total' => $total,
        ], fn ($value) => $value !== null));
    }

    /** Nouvel avancement : écrit au plus une fois par seconde, sauf si $force. */
    public function advance(Operation $operation, int $processed, ?int $succeeded = null, ?int $failed = null, bool $force = false): void
    {
        $operation->forceFill(array_filter([
            'processed' => $processed, 'succeeded' => $succeeded, 'failed' => $failed,
        ], fn ($value) => $value !== null));

        $last = $this->lastWrite[$operation->id] ?? 0.0;
        if (! $force && microtime(true) - $last < self::THROTTLE_SECONDS) {
            return;
        }

        $this->write($operation, $operation->only(['processed', 'succeeded', 'failed']));
    }

    /**
     * Enveloppe la fonction appliquée à chaque ligne d'un export : chaque appel
     * compte une ligne réellement écrite dans le fichier.
     */
    public function counting(Operation $operation, \Closure $callback): \Closure
    {
        $done = 0;

        return function (...$arguments) use ($operation, $callback, &$done) {
            $this->advance($operation, ++$done);

            return $callback(...$arguments);
        };
    }

    /**
     * Fin normale : « Terminé », avec les résultats chiffrés et le lien vers la page des résultats.
     * Une opération déjà en échec n'est jamais repassée en « Terminé ».
     */
    public function finish(Operation $operation, array $summary = [], ?string $resultUrl = null): void
    {
        if ($this->currentStatus($operation) === 'failed') {
            return;
        }

        $this->write($operation, [
            'status' => 'completed',
            'stage' => null,
            'indeterminate' => false,
            'processed' => $operation->processed ?? 0,
            'succeeded' => $operation->succeeded,
            'failed' => $operation->failed,
            'summary' => $summary === [] ? null : $summary,
            'result_url' => $resultUrl,
            'finished_at' => now(),
        ]);
    }

    /** Échec : message compréhensible pour l'utilisateur (le détail technique va dans les logs). */
    public function fail(Operation $operation, string $message): void
    {
        if ($this->currentStatus($operation) === 'completed') {
            return;
        }

        $this->write($operation, [
            'status' => 'failed',
            'error_message' => Str::limit($message, 1000),
            'indeterminate' => false,
            'finished_at' => now(),
        ]);
    }

    /** Échec d'une opération dont on ne connaît que l'id (méthode failed() d'un job). */
    public function failById(?int $operationId, string $message): void
    {
        $operation = $operationId !== null ? Operation::on($this->connection())->find($operationId) : null;
        if ($operation !== null) {
            $this->fail($operation, $message);
        }
    }

    /** Nom de la connexion utilisée pour écrire (voir le commentaire de la classe). */
    public function connection(): string
    {
        return $this->connectionFor(config('database.default'));
    }

    /** Connexion d'écriture à utiliser quand la connexion principale s'appelle $default. */
    public function connectionFor(string $default): string
    {
        if (config("database.connections.{$default}.driver") === 'sqlite') {
            return $default;
        }
        if (config('database.connections.'.self::CONNECTION) === null) {
            config(['database.connections.'.self::CONNECTION => config("database.connections.{$default}")]);
        }

        return self::CONNECTION;
    }

    /** Écrit immédiatement (et note un signe de vie). */
    private function write(Operation $operation, array $attributes): void
    {
        $attributes['heartbeat_at'] = now();
        $operation->setConnection($this->connection())->forceFill($attributes)->save();
        $this->lastWrite[$operation->id] = microtime(true);
    }

    private function currentStatus(Operation $operation): ?string
    {
        return Operation::on($this->connection())->whereKey($operation->id)->value('status');
    }
}
