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
use App\Models\MatchingExport;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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
 */
class GenerateMatchingExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 1;

    public int $timeout = 0;

    public function __construct(
        public MatchingExport $matchingExport,
    ) {
    }

    public function handle(): void
    {
        $this->matchingExport->update([
            'status' => 'processing',
        ]);

        $format = $this->matchingExport->format;
        $filters = $this->matchingExport->filters ?? [];
        $extension = match ($format) {
            'csv' => 'csv',
            'xlsx' => 'xlsx',
            'pdf' => 'pdf',
            default => 'csv',
        };

        $fileName = 'matching-results-'.$this->matchingExport->id.'-'.now()->format('Ymd-His').'.'.$extension;
        $disk = 'local';
        $directory = 'exports/matching-results';

        $query = \App\Models\MatchingResult::query()
            ->with([
                'matchingRule',
                'matchedByUser',
                'matchingDetails.normalizedTransaction.transaction.source',
            ])
            ->orderByDesc('id');

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

        $sideColumns = fn (\App\Models\MatchingResult $result, string $side) => $result->matchingDetails
            ->where('side', $side)
            ->map(fn ($detail) => $detail->normalizedTransaction)
            ->filter();

        $export = new GenericTableExport(
            $query,
            [
                __('Règle'), __('Statut'), __('Confiance'), __('Traité par'), __('Date'),
                __('Source A'), __('Référence A'), __('Montant A'), __('Date A'),
                __('Source B'), __('Référence B'), __('Montant B'), __('Date B'),
            ],
            function (\App\Models\MatchingResult $result) use ($sideColumns) {
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

            Excel::store($export, "{$directory}/{$fileName}", $disk);

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

            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        $this->matchingExport->update([
            'status' => 'failed',
            'error_message' => $e->getMessage(),
        ]);
    }
}
