<?php

namespace App\Http\Controllers\Admin;

/**
 * Contrôleur de gestion des imports de fichiers.
 *
 * Responsabilités :
 *  - Upload et stockage sécurisé du fichier (détection de doublon par SHA-256)
 *  - Validation des en-têtes contre les mappings requis avant traitement
 *  - Déclenchement du ProcessImportJob
 *  - Garantie d'idempotie : un import ne peut être lancé qu'une seule fois
 *    (guard atomique sur job_dispatched_at)
 */
use App\Enums\ImportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreImportRequest;
use App\Jobs\ProcessImportJob;
use App\Models\ComparisonRun;
use App\Models\Import;
use App\Models\ImportRow;
use App\Models\Source;
use App\Models\SourceColumnMapping;
use App\Services\Import\ImportMappingVersion;
use App\Services\Import\MappingEngine;
use App\Services\Import\Readers\ImportRowReaderFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class ImportController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Import::class, 'import');
    }

    public function index(): View
    {
        return view('admin.imports.index');
    }

    public function data(): JsonResponse
    {
        $this->authorize('viewAny', Import::class);

        $imports = Import::query()->with(['source', 'importedByUser'])->select('imports.*');
        $mappingHashes = [];

        return DataTables::of($imports)
            ->addColumn('source', fn (Import $import) => $import->source?->code)
            ->addColumn('status', function (Import $import) use (&$mappingHashes) {
                $badge = sprintf('<span class="badge %s">%s</span>', $import->status->badgeClass(), $import->status->label());
                if ($import->source && $import->processed_rows > 0) {
                    $versions = app(ImportMappingVersion::class);
                    $mappingHashes[$import->source_id] ??= $versions->hash($versions->capture($import->source));
                    if ($versions->state($import, $mappingHashes[$import->source_id]) !== 'current') {
                        $badge .= ' <span class="badge bg-warning text-dark">'.e(__('Mapping à vérifier')).'</span>';
                    }
                }

                return $badge;
            })
            ->addColumn('duration', fn (Import $import) => $this->formatDuration($import))
            ->addColumn('uploaded_by', fn (Import $import) => $import->importedByUser?->name ?? '—')
            ->addColumn('actions', fn (Import $import) => view('admin.imports._actions', ['import' => $import])->render())
            ->rawColumns(['status', 'actions'])
            ->toJson();
    }

    public function create(): View
    {
        return view('admin.imports.create', [
            'sources' => Source::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreImportRequest $request, ImportRowReaderFactory $readers, MappingEngine $engine): RedirectResponse
    {
        $source = Source::query()->findOrFail($request->validated('source_id'));
        $file = $request->file('file');
        $hash = hash_file('sha256', $file->getRealPath());

        $duplicate = Import::query()->where('file_hash', $hash)->first();

        if ($duplicate && ! $request->boolean('confirmed_duplicate')) {
            return back()->withInput()->with(
                'duplicate_warning',
                __('Ce fichier semble déjà avoir été importé le :date. Soumettez à nouveau pour continuer quand même.', [
                    'date' => $duplicate->created_at->format('d/m/Y H:i'),
                ])
            );
        }

        $directory = "imports/{$source->code}";
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $storedPath = $file->storeAs($directory, $filename, 'local');

        $import = Import::query()->create([
            'source_id' => $source->id,
            'bank_id' => $source->bank_id,
            'original_filename' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'file_hash' => $hash,
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'status' => ImportStatus::Pending,
            'imported_by' => $request->user()->id,
        ]);

        $mappings = SourceColumnMapping::query()->where('source_id', $source->id)->get();
        $requiredMappings = $mappings->where('is_required', true);

        if ($requiredMappings->isEmpty()) {
            return redirect()
                ->route('admin.sources.mappings.edit', ['source' => $source, 'import' => $import->id])
                ->with('status', __('Aucun mapping n\'est configuré pour cette source. Veuillez associer les colonnes avant de lancer cet import.'));
        }

        $reader = $readers->make($source);
        $missing = $engine->validateHeaders($reader->headers(Storage::path($storedPath), $source->config ?? []), $requiredMappings);

        if ($missing !== []) {
            return redirect()
                ->route('admin.sources.mappings.edit', ['source' => $source, 'import' => $import->id])
                ->with('status', __('La structure du fichier a changé (colonnes manquantes : :columns). Veuillez ajuster le mapping avant de lancer cet import.', [
                    'columns' => implode(', ', $missing),
                ]));
        }

        app(ImportMappingVersion::class)->freeze($import);
        $import->update(['job_dispatched_at' => now()]);
        ProcessImportJob::dispatch($import->id);

        return redirect()->route('admin.imports.show', $import)->with('status', __('Import lancé avec succès.'));
    }

    public function show(Import $import, Request $request): View
    {
        $import->load(['source', 'importedByUser']);

        $rows = ImportRow::query()
            ->where('import_id', $import->id)
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->orderBy('row_number')
            ->paginate(25)
            ->withQueryString();

        $mappingState = app(ImportMappingVersion::class)->state($import);
        $ledger = DB::table('normalized_transactions as n')->join('transactions as t', 't.id', '=', 'n.transaction_id')
            ->where('t.import_id', $import->id)->whereNull('t.deleted_at')->whereNull('n.deleted_at')
            ->selectRaw('n.matching_status, COUNT(*) AS rows_count, SUM(n.normalized_amount_millimes) AS amount_millimes')
            ->groupBy('n.matching_status')->get();
        $runs = ComparisonRun::with(['importA', 'importB'])->where(fn ($q) => $q->where('import_a_id', $import->id)->orWhere('import_b_id', $import->id))
            ->orderByDesc('id')->paginate(5, ['*'], 'runs_page')->withQueryString();
        $canResume = in_array($import->status->value, ['failed', 'processing'], true)
            && ($import->status->value === 'failed' || ($import->heartbeat_at ?? $import->updated_at)->lt(now()->subMinutes(5)));

        return view('admin.imports.show', compact('import', 'rows', 'mappingState', 'ledger', 'runs', 'canResume'));
    }

    public function process(Import $import, ImportRowReaderFactory $readers, MappingEngine $engine): RedirectResponse
    {
        $this->authorize('create', Import::class);

        $stale = $import->status === ImportStatus::Processing && ($import->heartbeat_at ?? $import->updated_at)->lt(now()->subMinutes(5));
        $resuming = $import->status === ImportStatus::Failed || $stale;
        if ($import->status !== ImportStatus::Pending && ! $resuming) {
            return redirect()->route('admin.imports.show', $import)->with('status', __('Cet import a déjà été traité.'));
        }

        if ($resuming && $import->mapping_snapshot === null && $import->rows()->exists()) {
            return back()->with('status', __('Reprise impossible sans version du mapping : renormaliser cet ancien import ou importer à nouveau le fichier.'));
        }
        $source = clone $import->source;
        $resetMapping = $resuming && ! $import->rows()->exists();
        $snapshot = $resetMapping ? null : $import->mapping_snapshot;
        if ($snapshot !== null) {
            $source->file_type = $snapshot['file_type'];
            $source->config = $snapshot['config'];
        }
        $mappings = $snapshot !== null
            ? app(ImportMappingVersion::class)->mappings($snapshot)
            : SourceColumnMapping::query()->where('source_id', $source->id)->get();
        $requiredMappings = $mappings->where('is_required', true);

        $reader = $readers->make($source);
        $missing = $engine->validateHeaders($reader->headers(Storage::path($import->stored_path), $source->config ?? []), $requiredMappings);

        if ($missing !== []) {
            return redirect()
                ->route('admin.sources.mappings.edit', ['source' => $source, 'import' => $import->id])
                ->with('status', __('Colonnes requises toujours manquantes : :columns', ['columns' => implode(', ', $missing)]));
        }

        // store() dispatche déjà le job -- le statut reste "pending" jusqu'à
        // ce qu'un worker le prenne. Sans cette garde, un double-clic ou un
        // rechargement pendant la file d'attente pourrait lancer un second
        // job et doubler les insertions. La mise à jour conditionnelle
        // (pas de read-then-write) rend la garantie "une seule fois"
        // atomique contre les requêtes concurrentes.
        $claimed = DB::transaction(function () use ($import, $resuming, $resetMapping) {
            $current = Import::whereKey($import->id)->lockForUpdate()->firstOrFail();
            $stale = $current->status === ImportStatus::Processing && ($current->heartbeat_at ?? $current->updated_at)->lt(now()->subMinutes(5));
            if ($resuming ? ($current->status !== ImportStatus::Failed && ! $stale) : ($current->status !== ImportStatus::Pending || $current->job_dispatched_at !== null)) {
                return 0;
            }
            app(ImportMappingVersion::class)->freeze($current, $resetMapping);
            $current->update(['status' => ImportStatus::Pending, 'job_dispatched_at' => now(), 'heartbeat_at' => now()]);

            return 1;
        });

        if ($claimed === 0) {
            return redirect()->route('admin.imports.show', $import)->with('status', __('Cet import a déjà été lancé — veuillez patienter pendant son traitement.'));
        }

        ProcessImportJob::dispatch($import->id);

        return redirect()->route('admin.imports.show', $import)->with('status', __('Import relancé avec succès.'));
    }

    public function destroy(Import $import): RedirectResponse
    {
        $this->authorize('delete', $import);

        $import->delete();

        return redirect()->route('admin.imports.index')->with('status', __('Import supprimé avec succès.'));
    }

    private function formatDuration(Import $import): string
    {
        if (! $import->started_at) {
            return '—';
        }

        $end = $import->finished_at ?? now();

        return $import->started_at->diffForHumans($end, true);
    }
}
