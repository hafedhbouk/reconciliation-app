<?php

namespace App\Jobs;

/**
 * Génère un export de résultats de rapprochement (CSV, XLSX ou PDF).
 *
 * Le fichier est écrit sur le disque local (storage/app/exports) et le
 * chemin est stocké dans matching_exports.file_path. L'utilisateur
 * déclencheur est notifié à la fin du traitement.
 *
 * Formats supportés :
 * - csv : streamé ligne par ligne, peu gourmand en mémoire
 * - xlsx : chargé en mémoire par PhpSpreadsheet, réservé aux volumes
 *          raisonnables (< ~50k lignes)
 * - pdf : idem, limité aux volumes raisonnables
 */
use App\Exports\GenericTableExport;
use App\Exports\UnmatchedExport;
use App\Exceptions\UnmatchedExportUnavailableException;
use App\Models\MatchingExport;
use App\Models\MatchingResult;
use App\Models\UnmatchedSnapshot;
use App\Services\Matching\SnapshotRows;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Génère un export de résultats de rapprochement en arrière-plan.
 *
 * Le fichier est écrit sur le disque local (storage/app/exports) et le
 * chemin est stocké dans matching_exports.file_path. L'utilisateur
 * déclencheur est notifié à la fin du traitement.
 *
 * Formats supportés :
 * - csv : streamé ligne par ligne, peu gourmand en mémoire
 * - xlsx : chargé en mémoire par Maatwebsite, réservé aux volumes
 *          raisonnables (< ~50k lignes) pour ne pas exploser la
 *          memory_limit du worker
 * - pdf : idem, limité aux volumes raisonnables
 *
 * Remarque : malgré la phrase « est notifié » ci-dessus, aucune notification
 * n'est envoyée aujourd'hui ; l'utilisateur suit l'avancement sur la page « Exports ».
 *
 * Deux types d'export passent par ce job :
 *   - les résultats de rapprochement (cas par défaut, filtrés comme à l'écran) ;
 *   - les écarts d'une comparaison de fichiers (filters.type = 'unmatched'), voir handleUnmatched().
 * (Le fonctionnement général des jobs est expliqué dans ProcessImportJob.php.)
 */
class GenerateMatchingExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 1;

    public int $timeout = 0;

    public function __construct(
        public MatchingExport $matchingExport,
    ) {}

    public function handle(): void
    {
        $this->matchingExport->update([
            'status' => 'processing',
        ]);

        $format = $this->matchingExport->format;
        $filters = $this->matchingExport->filters ?? [];
        if (($filters['type'] ?? null) === 'unmatched') {
            $this->handleUnmatched($format, (int) ($filters['snapshot_id'] ?? 0));

            return;
        }

        $extension = match ($format) {
            'csv' => 'csv',
            'xlsx' => 'xlsx',
            'pdf' => 'pdf',
            default => 'csv',
        };

        $fileName = 'matching-results-'.$this->matchingExport->id.'-'.now()->format('Ymd-His').'.'.$extension;
        $disk = 'local';
        $directory = 'exports/matching-results';

        // Requête des résultats. with([...]) précharge les relations en quelques requêtes
        // groupées, au lieu d'une requête par résultat (problème dit « N+1 »).
        $query = MatchingResult::query()
            ->with([
                'matchingRule',
                'matchedByUser',
                'matchingDetails.normalizedTransaction.transaction.source',
            ])
            ->orderByDesc('id');

        // On rejoue les filtres choisis par l'utilisateur à l'écran.
        if (! empty($filters['matching_rule_id'])) {
            $query->where('matching_rule_id', $filters['matching_rule_id']);
        }

        if (! empty($filters['batch_reference'])) {
            $query->where('batch_reference', $filters['batch_reference']);
        }

        if (! empty($filters['matched_at_from'])) {
            $query->where('matched_at', '>=', $filters['matched_at_from'].' 00:00:00');
        }

        if (! empty($filters['matched_at_to'])) {
            $query->where('matched_at', '<=', $filters['matched_at_to'].' 23:59:59');
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Fonction utilitaire : les transactions d'un côté ('a' ou 'b') d'un résultat.
        $sideColumns = fn (MatchingResult $result, string $side) => $result->matchingDetails
            ->where('side', $side)
            ->map(fn ($detail) => $detail->normalizedTransaction)
            ->filter();

        // GenericTableExport reçoit : la requête, les titres de colonnes, et une fonction
        // qui transforme un résultat en ligne du fichier. Un groupe de plusieurs
        // transactions est affiché sur une seule ligne, valeurs séparées par « ; ».
        $export = new GenericTableExport(
            $query,
            [
                __('Règle'), __('Statut'), __('Confiance'), __('Traité par'), __('Date'),
                __('Source A'), __('Référence A'), __('Montant A'), __('Date A'),
                __('Source B'), __('Référence B'), __('Montant B'), __('Date B'),
            ],
            function (MatchingResult $result) use ($sideColumns) {
                $sideA = $sideColumns($result, 'a');
                $sideB = $sideColumns($result, 'b');

                return [
                    $result->matchingRule?->name ?? __('Rapprochement manuel'),
                    $result->status->label(),
                    $result->confidence_score,
                    $result->matchedByUser?->name ?? __('Automatique'),
                    $result->matched_at?->format('d/m/Y H:i'),
                    $sideA->map(fn ($nt) => $nt->transaction?->source?->code)->unique()->implode('; '),
                    $sideA->map(fn ($nt) => $nt->normalized_reference)->implode('; '),
                    $sideA->map(fn ($nt) => $nt->normalized_amount_millimes)->implode('; '),
                    $sideA->map(fn ($nt) => $nt->normalized_date?->format('d/m/Y'))->implode('; '),
                    $sideB->map(fn ($nt) => $nt->transaction?->source?->code)->unique()->implode('; '),
                    $sideB->map(fn ($nt) => $nt->normalized_reference)->implode('; '),
                    $sideB->map(fn ($nt) => $nt->normalized_amount_millimes)->implode('; '),
                    $sideB->map(fn ($nt) => $nt->normalized_date?->format('d/m/Y'))->implode('; '),
                ];
            },
        );

        try {
            $writerType = match ($format) {
                'csv' => ExcelFormat::CSV,
                'xlsx' => ExcelFormat::XLSX,
                'pdf' => ExcelFormat::DOMPDF,
            };

            // Excel::store (paquet maatwebsite/excel) génère le fichier et l'écrit sur le disque.
            Excel::store($export, "{$directory}/{$fileName}", $disk, $writerType);

            $this->matchingExport->update([
                'status' => 'completed',
                'file_path' => "{$directory}/{$fileName}",
                'completed_at' => now(),
            ]);
        } catch (Throwable $e) {
            $this->matchingExport->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            // On relance l'erreur pour que Laravel enregistre le job comme échoué.
            throw $e;
        }
    }

    /** Appelée par Laravel si le job échoue : l'export passe en « échec » avec le message. */
    public function failed(Throwable $e): void
    {
        $this->matchingExport->update([
            'status' => 'failed',
            'error_message' => $e->getMessage(),
        ]);
    }

    /** Export des écarts d'une comparaison de fichiers (lignes présentes d'un seul côté). */
    private function handleUnmatched(string $format, int $snapshotId): void
    {
        $snapshot = UnmatchedSnapshot::query()->with(['importA', 'importB'])->findOrFail($snapshotId);
        if ($snapshot->status !== 'completed' || ! $snapshot->importA || ! $snapshot->importB) {
            throw new UnmatchedExportUnavailableException('La comparaison des différences n’est plus disponible.');
        }

        app(SnapshotRows::class)->ensureStored($snapshot);
        $query = DB::table('unmatched_snapshot_rows')->where('snapshot_id', $snapshot->id)
            ->orderBy('side')->orderBy('normalized_transaction_id');
        $header = ['Côté exclusif', 'Fichier A', 'Fichier B', 'Calculé le', 'Source', 'Référence', 'Montant (millimes)', 'Date'];
        $map = function ($record) use ($snapshot): array {
            $row = json_decode($record->data, true, 512, JSON_THROW_ON_ERROR);

            return [strtoupper($record->side), $snapshot->importA->original_filename,
                $snapshot->importB->original_filename, $snapshot->completed_at?->format('d/m/Y H:i:s'),
                $row['source'] ?? '', $row['reference'] ?? $row['primary_key_value'] ?? '',
                (string) ($row['amount_millimes'] ?? ''), $row['date'] ?? ''];
        };
        // Sécurité (« injection de formule ») : une cellule qui commence par =, +, - ou @
        // serait exécutée comme une formule par Excel. On la préfixe d'une apostrophe
        // pour qu'elle reste du texte. Exception : la colonne 6, les montants
        // (entiers éventuellement négatifs), qu'on laisse en nombre.
        $rows = $query->get()->map($map)->map(function (array $values): array {
            return array_map(function ($value, $index) {
                $value = (string) $value;

                if ($index === 6 && preg_match('/^-?\d+$/', $value)) {
                    return $value;
                }

                return preg_match('/^[\s]*[=+@-]|^[\t\r\n]/u', $value) ? "'".$value : $value;
            }, $values, array_keys($values));
        })->all();
        $fileName = 'differences-'.$snapshot->id.'-'.$this->matchingExport->id.'.'.$format;

        try {
            $writerType = match ($format) {
                'csv' => ExcelFormat::CSV,
                'xlsx' => ExcelFormat::XLSX,
                'pdf' => ExcelFormat::DOMPDF,
            };
            Excel::store(new UnmatchedExport([$header, ...$rows]), "exports/reconciliation/{$fileName}", 'local', $writerType);
            $this->matchingExport->update([
                'status' => 'completed',
                'file_path' => "exports/reconciliation/{$fileName}",
                'completed_at' => now(),
            ]);
        } catch (Throwable $e) {
            $this->matchingExport->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            throw $e;
        }
    }
}
