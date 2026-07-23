<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ImportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreImportRequest;
use App\Jobs\ProcessImportJob;
use App\Models\Import;
use App\Models\ImportRow;
use App\Models\Source;
use App\Models\SourceColumnMapping;
use App\Services\Import\MappingEngine;
use App\Services\Import\Readers\ImportRowReaderFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        return DataTables::of($imports)
            ->addColumn('source', fn (Import $import) => $import->source?->code)
            ->addColumn('status', fn (Import $import) => sprintf(
                '<span class="badge %s">%s</span>',
                $import->status->badgeClass(),
                $import->status->label()
            ))
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

        return view('admin.imports.show', compact('import', 'rows'));
    }

    public function process(Import $import, ImportRowReaderFactory $readers, MappingEngine $engine): RedirectResponse
    {
        $this->authorize('create', Import::class);

        if ($import->status !== ImportStatus::Pending) {
            return redirect()->route('admin.imports.show', $import)->with('status', __('Cet import a déjà été traité.'));
        }

        $source = $import->source;
        $mappings = SourceColumnMapping::query()->where('source_id', $source->id)->get();
        $requiredMappings = $mappings->where('is_required', true);

        $reader = $readers->make($source);
        $missing = $engine->validateHeaders($reader->headers(Storage::path($import->stored_path), $source->config ?? []), $requiredMappings);

        if ($missing !== []) {
            return redirect()
                ->route('admin.sources.mappings.edit', ['source' => $source, 'import' => $import->id])
                ->with('status', __('Colonnes requises toujours manquantes : :columns', ['columns' => implode(', ', $missing)]));
        }

        ProcessImportJob::dispatch($import->id);

        return redirect()->route('admin.imports.show', $import)->with('status', __('Import relancé avec succès.'));
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
