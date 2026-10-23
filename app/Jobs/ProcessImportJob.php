<?php

namespace App\Jobs;

/**
 * Job asynchrone qui exécute un import complet.
 *
 * Lit le fichier via le lecteur adapté (CSV/XLSX), applique les mappings
 * de colonnes de la Source, insère les transactions et leurs snapshots
 * normalisés par chunks pour rester mémoire-safe sur les gros fichiers.
 *
 * Choix d'architecture : insertions en masse via query builder (pas de
 * Eloquent ::create par ligne) pour éviter des milliers de logs
 * d'audit/événements HasUserstamps pour un seul fichier. Les champs
 * created_by/updated_by sont renseignés manuellement dans les arrays
 * d'insertion.
 *
 * ---------------------------------------------------------------------------
 * GUIDE DÉBUTANT : qu'est-ce qu'un « job » ? (valable pour tout app/Jobs)
 * ---------------------------------------------------------------------------
 * Un job est une tâche longue exécutée EN ARRIÈRE-PLAN, hors de la requête
 * web. Importer 80 000 lignes prend plusieurs minutes : le navigateur ne
 * peut pas attendre.
 *   1. Le contrôleur écrit `ProcessImportJob::dispatch($import->id);`.
 *      Le job est enregistré dans la table `jobs` (la « file d'attente »).
 *   2. Un processus séparé, le « worker » (`php artisan queue:work`),
 *      prend les jobs de la file un par un et appelle leur méthode handle().
 *   3. Si handle() lève une erreur, Laravel appelle failed().
 * Sans worker démarré, les jobs restent dans la file et rien ne se passe.
 */
use App\Enums\ImportRowStatus;
use App\Enums\ImportStatus;
use App\Exceptions\Import\MissingRequiredFieldException;
use App\Exceptions\Import\RowTransformException;
use App\Models\Import;
use App\Models\Operation;
use App\Models\Source;
use App\Models\SourceColumnMapping;
use App\Notifications\ImportProcessedNotification;
use App\Services\Import\ImportMappingVersion;
use App\Services\Import\MappingEngine;
use App\Services\Import\Readers\ImportRowReaderFactory;
use App\Services\Import\TransactionNormalizer;
use App\Services\OperationTracker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;
use Throwable;

/**
 * Reads a Source's file through its saved SourceColumnMapping rows and lands
 * Transaction + NormalizedTransaction rows, chunked to stay memory-safe on
 * 80k+ row files.
 *
 * Trade-off (intentional, not an oversight): rows are written via
 * query-builder bulk insert(), not one Eloquent ::create() per row — so
 * HasUserstamps/Auditable never fire for ImportRow/Transaction/
 * NormalizedTransaction. created_by/updated_by are set manually in the
 * insert arrays instead, and audit-trail granularity is one Import lifecycle
 * entry (the Import model's own ->update() calls below DO go through
 * Eloquent and ARE audited normally), not tens of thousands of audit_logs
 * rows for a single file.
 */
class ProcessImportJob implements ShouldQueue // ShouldQueue = « exécute-moi dans la file d'attente »
{
    // Traits fournis par Laravel pour les jobs (dispatch(), accès à la file, sérialisation).
    use Dispatchable, InteractsWithQueue, SerializesModels;

    /** Une seule tentative : relancer automatiquement un import à moitié fait pourrait créer des doublons. */
    public int $tries = 1;

    /** 0 = pas de limite de durée (un gros fichier peut prendre plus de 10 minutes). */
    public int $timeout = 0;

    /**
     * On ne passe que l'id de l'import (pas l'objet entier) : le job est
     * enregistré en base, puis l'import est relu au moment de l'exécution.
     */
    public function __construct(
        public int $importId,
        public ?int $operationId = null, // suivi « Mes opérations » créé au clic (voir OperationTracker)
    ) {}

    /**
     * Point d'entrée appelé par le worker. Les paramètres sont fournis
     * automatiquement par Laravel (injection de dépendances).
     */
    public function handle(ImportRowReaderFactory $readerFactory, MappingEngine $engine, TransactionNormalizer $normalizer): void
    {
        // --- Étape 1 : charger l'import et ignorer ceux déjà terminés ---
        // with('source') charge la source en même temps (une requête de moins plus tard).
        $import = Import::query()->with('source')->findOrFail($this->importId);
        if (in_array($import->status, [ImportStatus::Completed, ImportStatus::PartiallyCompleted], true)) {
            return;
        }

        // Suivi de l'avancement (« Mes opérations ») : toute erreur imprévue y est notée avant d'être relancée.
        $tracker = app(OperationTracker::class);
        $operation = $tracker->resolve($this->operationId, 'import', 'Import '.$import->original_filename, $import->imported_by, $import);
        try {
            $this->run($import, $operation, $tracker, $readerFactory, $engine, $normalizer);
        } catch (Throwable $e) {
            $tracker->fail($operation, 'Import interrompu : '.$e->getMessage());

            throw $e;
        }
    }

    /** Étapes 2 à 7 de l'import (voir handle()). */
    private function run(Import $import, Operation $operation, OperationTracker $tracker, ImportRowReaderFactory $readerFactory, MappingEngine $engine, TransactionNormalizer $normalizer): void
    {
        // --- Étape 2 : utiliser la configuration FIGÉE au lancement de l'import ---
        // (voir ImportMappingVersion). On travaille sur une copie de la source,
        // avec les réglages de la copie figée, sans toucher à la source en base.
        $versions = app(ImportMappingVersion::class);
        $import = $versions->freeze($import);
        $source = clone $import->source;
        $source->file_type = $import->mapping_snapshot['file_type'];
        $source->config = $import->mapping_snapshot['config'];
        $source->bank_id = $import->mapping_snapshot['bank_id'] ?? null;
        $source->default_currency_id = $import->mapping_snapshot['default_currency_id'] ?? null;
        $mappings = $versions->mappings($import->mapping_snapshot);
        $requiredMappings = $mappings->where('is_required', true);

        // --- Étape 3 : vérifier que le fichier n'a pas été remplacé ---
        $reader = $readerFactory->make($source);
        $path = Storage::path($import->stored_path); // chemin complet sur le disque
        $sourceConfig = $source->config ?? [];
        $fileHash = hash_file('sha256', $path);
        // Si l'import est une reprise, l'empreinte doit être celle du premier passage.
        DB::transaction(function () use ($import, $fileHash) {
            $current = Import::whereKey($import->id)->lockForUpdate()->firstOrFail();
            if ($current->processing_file_hash !== null && ! hash_equals($current->processing_file_hash, $fileHash)) {
                throw new \RuntimeException('Le fichier a changé depuis le début du traitement : reprise refusée.');
            }
            $current->update(['processing_file_hash' => $fileHash]);
        });

        // --- Étape 4 : refuser le fichier si des colonnes obligatoires manquent ---
        $missing = $engine->validateHeaders($reader->headers($path, $sourceConfig), $requiredMappings);

        if ($missing !== []) {
            $import->update([
                'status' => ImportStatus::Failed,
                'error_summary' => 'Colonnes requises manquantes : '.implode(', ', $missing),
                'finished_at' => now(),
            ]);
            $tracker->fail($operation, $import->error_summary);

            // `?->` (« nullsafe ») : n'appelle notify() que si l'utilisateur existe.
            $import->importedByUser?->notify(new ImportProcessedNotification($import));

            return;
        }

        $import->update(['status' => ImportStatus::Processing, 'started_at' => $import->started_at ?? now(), 'heartbeat_at' => now(), 'error_summary' => null, 'finished_at' => null]);

        // --- Étape 5 : lire et enregistrer le fichier par paquets (« chunks ») ---
        $chunkSize = config('imports.chunk_size', 500); // réglage de config/imports.php, 500 par défaut
        $userId = $import->imported_by;

        // LazyCollection : collection « paresseuse » qui ne lit les lignes qu'au moment où on en a besoin.
        // chunk(500) les regroupe par paquets de 500 : on n'a jamais plus de 500 lignes en mémoire.
        // Total réel lu dans le fichier (voir ImportRowReader::countRows), puis avancement après chaque paquet enregistré.
        $tracker->start($operation, $reader->countRows($path, $sourceConfig), 'Lecture et enregistrement des lignes');
        $rows = LazyCollection::make(fn () => yield from $reader->read($path, $sourceConfig));
        foreach ($rows->chunk($chunkSize) as $chunk) {
            $this->processChunk($chunk, $import, $source, $mappings, $engine, $normalizer, $userId);
            $counters = Import::query()->whereKey($import->id)->first(['processed_rows', 'success_rows', 'error_rows']);
            $tracker->advance($operation, $counters->processed_rows, $counters->success_rows, $counters->error_rows);
        }

        // --- Étape 6 : calculer le statut final ---
        DB::transaction(function () use ($import) {
            $current = Import::whereKey($import->id)->lockForUpdate()->firstOrFail();
            $this->syncCounters($current);
            $current->update([
                // `match (true)` : la première condition vraie l'emporte.
                'status' => match (true) {
                    $current->error_rows === 0 => ImportStatus::Completed,          // aucune erreur
                    $current->success_rows === 0 => ImportStatus::Failed,           // aucune ligne valide
                    default => ImportStatus::PartiallyCompleted,                    // un mélange des deux
                },
                'finished_at' => now(), 'heartbeat_at' => now(),
            ]);
        });
        $import->refresh(); // relit l'import depuis la base pour avoir les valeurs à jour

        $tracker->advance($operation, $import->processed_rows, $import->success_rows, $import->error_rows, force: true);
        if ($import->status === ImportStatus::Failed) {
            $tracker->fail($operation, "Aucune ligne valide : {$import->error_rows} ligne(s) rejetée(s).");
        } else {
            $tracker->finish($operation, ['acceptees' => $import->success_rows, 'rejetees' => $import->error_rows],
                route('admin.imports.show', $import, false));
        }

        // --- Étape 7 : prévenir l'utilisateur (cloche de notifications) ---
        $import->importedByUser?->notify(new ImportProcessedNotification($import));
    }

    /**
     * Traite un paquet de lignes dans UNE transaction SQL : soit tout le
     * paquet est enregistré, soit rien (en cas d'erreur technique).
     *
     * Ordre d'insertion imposé par les clés étrangères :
     *   import_rows -> transactions (qui pointe vers import_rows)
     *               -> normalized_transactions (qui pointe vers transactions)
     *
     * @param  LazyCollection<int,array<string,mixed>>  $chunk  row_number => raw row
     * @param  Collection<int,SourceColumnMapping>  $mappings
     * @return array{0:int,1:int,2:int} [processed, success, error] counts for this chunk
     */
    private function processChunk(
        LazyCollection $chunk,
        Import $import,
        Source $source,
        Collection $mappings,
        MappingEngine $engine,
        TransactionNormalizer $normalizer,
        ?int $userId,
    ): array {
        return DB::transaction(function () use ($chunk, $import, $source, $mappings, $engine, $normalizer, $userId) {
            $current = Import::whereKey($import->id)->lockForUpdate()->firstOrFail();
            $chunk = $chunk->collect();
            if ($current->mapping_hash !== $import->mapping_hash) {
                throw new \RuntimeException('Le mapping de cet import a changé pendant le traitement.');
            }

            // Reprise après interruption : on saute les lignes déjà enregistrées lors d'un passage précédent.
            // flip() inverse clés et valeurs pour pouvoir tester rapidement avec ->has($numero).
            $existing = DB::table('import_rows')->where('import_id', $import->id)
                ->whereIn('row_number', $chunk->keys())->lockForUpdate()->pluck('row_number')->flip();
            $chunk = $chunk->reject(fn ($row, $number) => $existing->has($number));
            if ($chunk->isEmpty()) {
                $this->syncCounters($current);

                return [0, 0, 0];
            }
            $now = now();
            $importRowsInsert = [];               // lignes à insérer dans import_rows
            $transactionRowsByRowNumber = [];     // lignes à insérer dans transactions (lignes valides seulement)
            $normalizedSnapshotsByRowNumber = []; // données normalisées correspondantes
            $successCount = 0;
            $errorCount = 0;

            foreach ($chunk as $rowNumber => $rawRow) {
                // Colonnes communes à une ligne valide et à une ligne en erreur.
                $base = [
                    'import_id' => $import->id,
                    'row_number' => $rowNumber,
                    'raw_data' => json_encode($rawRow),
                    'created_at' => $now,
                    'updated_at' => $now,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ];

                try {
                    // Les 3 étapes de transformation d'une ligne (voir MappingEngine et TransactionNormalizer).
                    $transformed = $engine->transformRow($rawRow, $mappings);
                    $transactionRow = $normalizer->buildTransactionRow($transformed, $source, $import, $userId);
                    $snapshot = $normalizer->computeNormalizedSnapshot($transactionRow);

                    $importRowsInsert[$rowNumber] = $base + [
                        'transformed_data' => json_encode($transformed),
                        'normalized_data' => json_encode($normalizer->importRowNormalizedData($snapshot)),
                        'status' => ImportRowStatus::Imported->value,
                        'error_message' => null,
                    ];

                    $transactionRowsByRowNumber[$rowNumber] = $transactionRow;
                    $normalizedSnapshotsByRowNumber[$rowNumber] = $snapshot;
                    $successCount++;
                } catch (MissingRequiredFieldException|RowTransformException $e) {
                    // Ligne invalide : on la garde avec son message d'erreur, et on continue avec les suivantes.
                    $importRowsInsert[$rowNumber] = $base + [
                        'transformed_data' => null,
                        'normalized_data' => null,
                        'status' => ImportRowStatus::Error->value,
                        'error_message' => $e->getMessage(),
                    ];

                    $errorCount++;
                }
            }

            // 1) Insérer toutes les lignes du paquet en UNE requête SQL.
            DB::table('import_rows')->insert(array_values($importRowsInsert));

            // Une insertion en masse ne renvoie pas les id créés : on les relit
            // (numéro de ligne => id) pour relier les transactions à leur ligne.
            $importRowIdsByRowNumber = DB::table('import_rows')
                ->where('import_id', $import->id)
                ->whereIn('row_number', array_keys($importRowsInsert))
                ->pluck('id', 'row_number');

            if ($transactionRowsByRowNumber !== []) {
                // 2) Insérer les transactions, chacune reliée à sa ligne d'import.
                $transactionsInsert = [];
                foreach ($transactionRowsByRowNumber as $rowNumber => $transactionRow) {
                    $transactionRow['import_row_id'] = $importRowIdsByRowNumber[$rowNumber];
                    $transactionsInsert[$rowNumber] = $transactionRow;
                }

                DB::table('transactions')->insert(array_values($transactionsInsert));

                // Même astuce : on relit les id des transactions créées (import_row_id => id).
                $transactionIdsByImportRowId = DB::table('transactions')
                    ->where('import_id', $import->id)
                    ->whereIn('import_row_id', $importRowIdsByRowNumber->only(array_keys($transactionsInsert))->all())
                    ->pluck('id', 'import_row_id');

                // 3) Insérer les transactions normalisées, chacune reliée à sa transaction.
                $normalizedInsert = [];
                foreach ($transactionsInsert as $rowNumber => $transactionRow) {
                    $importRowId = $importRowIdsByRowNumber[$rowNumber];
                    $transactionId = $transactionIdsByImportRowId[$importRowId];
                    $normalizedInsert[] = $normalizer->buildNormalizedRow(
                        $transactionId,
                        $normalizedSnapshotsByRowNumber[$rowNumber],
                        $userId
                    );
                }

                DB::table('normalized_transactions')->insert($normalizedInsert);
            }

            $this->syncCounters($current);

            return [count($importRowsInsert), $successCount, $errorCount];
        });
    }

    /**
     * Recalcule les compteurs de l'import (total, valides, en erreur) à partir
     * de la table import_rows. Le calcul se fait en SQL : c'est la base qui
     * compte, et non une variable PHP qui pourrait se tromper lors d'une reprise.
     */
    private function syncCounters(Import $import): void
    {
        $counts = DB::table('import_rows')->where('import_id', $import->id)
            ->selectRaw("COUNT(*) AS total, COALESCE(SUM(CASE WHEN status = 'imported' THEN 1 ELSE 0 END), 0) AS accepted, COALESCE(SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END), 0) AS rejected")->lockForUpdate()->first();
        // heartbeat_at = « signe de vie » : prouve que le job avance encore.
        $import->update(['total_rows' => (int) $counts->total, 'processed_rows' => (int) $counts->total,
            'success_rows' => (int) $counts->accepted, 'error_rows' => (int) $counts->rejected, 'heartbeat_at' => now()]);
    }

    /**
     * Appelée automatiquement par Laravel si handle() a échoué (erreur imprévue).
     * On marque l'import en échec, avec le message d'erreur, pour que
     * l'utilisateur le voie dans la liste des imports.
     */
    public function failed(Throwable $e): void
    {
        Import::query()->whereKey($this->importId)->whereNotIn('status', ['completed', 'partially_completed'])->update([
            'status' => ImportStatus::Failed->value,
            'error_summary' => $e->getMessage(),
            'job_dispatched_at' => null,
            'finished_at' => now(),
        ]);
        app(OperationTracker::class)->failById($this->operationId, 'Import interrompu : '.$e->getMessage());
    }
}
