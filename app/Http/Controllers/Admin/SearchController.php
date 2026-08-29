<?php

namespace App\Http\Controllers\Admin;

/**
 * Contrôleur de recherche globale dans les transactions normalisées.
 *
 * Permet de filtrer par source, référence, plage de montants, dates,
 * canal et statut de matching. L'export est limité en taille pour XLSX
 * et PDF (limite de mémoire PhpSpreadsheet/dompdf), seuls les CSV
 * restent illimités.
 */
use App\Exports\GenericTableExport;
use App\Http\Controllers\Controller;
use App\Models\NormalizedTransaction;
use App\Models\Source;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Yajra\DataTables\Facades\DataTables;

class SearchController extends Controller
{
    public function index(): View
    {
        $this->authorize('search.viewAny');

        return view('admin.search.index', [
            'sources' => Source::query()->orderBy('name')->get(),
        ]);
    }

    public function data(Request $request)
    {
        $this->authorize('search.viewAny');

        return DataTables::of($this->buildQuery($request))
            ->addColumn('source', fn (NormalizedTransaction $nt) => $nt->transaction?->source?->code)
            ->addColumn('canal', fn (NormalizedTransaction $nt) => $nt->transaction?->canal)
            ->addColumn('matching_status', fn (NormalizedTransaction $nt) => sprintf(
                '<span class="badge %s">%s</span>',
                $nt->matching_status->badgeClass(),
                $nt->matching_status->label()
            ))
            ->rawColumns(['matching_status'])
            ->toJson();
    }

    public function export(Request $request, string $format): BinaryFileResponse
    {
        $this->authorize('search.viewAny');
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 404);

        $query = $this->buildQuery($request);

        // CSV stream ligne par ligne et supporte de grands volumes (vérifié
        // sur ~150k lignes). XLSX (PhpSpreadsheet) et PDF (dompdf)
        // construisent d'abord un objet modèle complet en mémoire -- ce qui
        // épuise la memory_limit de PHP sur le même volume -- donc les deux
        // formats sont plafonnés à 1000 lignes.
        if (in_array($format, ['xlsx', 'pdf'], true)) {
            $query->limit(1000);
        }

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

        return Excel::download($export, "recherche.{$format}", $this->writerType($format));
    }

    private function buildQuery(Request $request): Builder
    {
        $validated = $request->validate([
            'source_id' => ['nullable', 'exists:sources,id'],
            'reference' => ['nullable', 'string', 'max:255'],
            'amount_min' => ['nullable', 'integer'],
            'amount_max' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'matching_status' => ['nullable', 'string'],
            'canal' => ['nullable', 'string', 'max:255'],
        ]);

        return NormalizedTransaction::query()
            ->with('transaction.source')
            ->whereHas('transaction', fn ($query) => $query
                ->when($validated['source_id'] ?? null, fn ($q, $sourceId) => $q->where('source_id', $sourceId))
                ->when($validated['canal'] ?? null, fn ($q, $canal) => $q->where('canal', 'like', "%{$canal}%")))
            ->when($validated['reference'] ?? null, fn ($query, $reference) => $query->where('normalized_reference', 'like', "%{$reference}%"))
            ->when($validated['amount_min'] ?? null, fn ($query, $min) => $query->where('normalized_amount_millimes', '>=', $min))
            ->when($validated['amount_max'] ?? null, fn ($query, $max) => $query->where('normalized_amount_millimes', '<=', $max))
            ->when($validated['date_from'] ?? null, fn ($query, $date) => $query->where('normalized_date', '>=', $date))
            ->when($validated['date_to'] ?? null, fn ($query, $date) => $query->where('normalized_date', '<=', $date))
            ->when($validated['matching_status'] ?? null, fn ($query, $status) => $query->where('matching_status', $status))
            ->orderByDesc('id');
    }

    private function writerType(string $format): string
    {
        return match ($format) {
            'csv' => ExcelFormat::CSV,
            'xlsx' => ExcelFormat::XLSX,
            'pdf' => ExcelFormat::DOMPDF,
        };
    }
}
