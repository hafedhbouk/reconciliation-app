<?php

namespace App\Jobs;

use App\Exports\GenericTableExport;
use App\Models\MatchingExport;
use App\Models\NormalizedTransaction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Génère un export de recherche multi-critères (CSV, XLSX ou PDF) en arrière-plan.
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
class GenerateSearchExportJob implements ShouldQueue
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

        $extension = match ($format) {
            'csv' => 'csv',
            'xlsx' => 'xlsx',
            'pdf' => 'pdf',
            default => 'csv',
        };

        $fileName = 'search-'.$this->matchingExport->id.'-'.now()->format('Ymd-His').'.'.$extension;
        $disk = 'local';
        $directory = 'exports/search';

        $query = $this->buildQuery($filters);

        $export = new GenericTableExport(
            $query,
            [__('Source'), __('Référence'), __('Référence secondaire'), __('Montant'), __('Date'), __('Canal'), __('Statut')],
            fn (NormalizedTransaction $nt) => [
                $nt->transaction?->source?->code,
                $nt->normalized_reference,
                $nt->transaction?->raw_payload['secondary_reference'] ?? null,
                $nt->normalized_amount_millimes,
                $nt->normalized_date?->format('d/m/Y'),
                $nt->transaction?->canal,
                $nt->matching_status->label(),
            ],
        );

        try {
            $writerType = match ($format) {
                'csv' => ExcelFormat::CSV,
                'xlsx' => ExcelFormat::XLSX,
                'pdf' => ExcelFormat::DOMPDF,
            };

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

    /**
     * @param array<string,mixed> $filters
     */
    private function buildQuery(array $filters): \Illuminate\Database\Eloquent\Builder
    {
        return NormalizedTransaction::query()
            ->with('transaction.source')
            ->whereHas('transaction', fn ($query) => $query
                ->when($filters['source_id'] ?? null, fn ($q, $sourceId) => $q->where('source_id', $sourceId))
                ->when($filters['canal'] ?? null, fn ($q, $canal) => $q->where('canal', 'like', "%{$canal}%")))
            ->when($filters['reference'] ?? null, fn ($query, $reference) => $query->where('normalized_reference', 'like', "%{$reference}%"))
            ->when($filters['amount_min'] ?? null, fn ($query, $min) => $query->where('normalized_amount_millimes', '>=', $min))
            ->when($filters['amount_max'] ?? null, fn ($query, $max) => $query->where('normalized_amount_millimes', '<=', $max))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->where('normalized_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->where('normalized_date', '<=', $date))
            ->when($filters['matching_status'] ?? null, fn ($query, $status) => $query->where('matching_status', $status))
            ->orderByDesc('id');
    }
}