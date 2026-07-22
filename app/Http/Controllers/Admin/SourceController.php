<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSourceRequest;
use App\Http\Requests\Admin\UpdateSourceRequest;
use App\Models\Bank;
use App\Models\Currency;
use App\Models\Source;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SourceController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Source::class, 'source');
    }

    public function index(): View
    {
        $sources = Source::query()->with(['bank', 'defaultCurrency'])->orderBy('name')->paginate(15);

        return view('admin.sources.index', compact('sources'));
    }

    public function create(): View
    {
        return view('admin.sources.create', $this->formData());
    }

    public function store(StoreSourceRequest $request): RedirectResponse
    {
        Source::query()->create($request->validated());

        return redirect()->route('admin.sources.index')->with('status', __('Source créée avec succès.'));
    }

    public function edit(Source $source): View
    {
        return view('admin.sources.edit', ['source' => $source] + $this->formData());
    }

    public function update(UpdateSourceRequest $request, Source $source): RedirectResponse
    {
        $source->update($request->validated());

        return redirect()->route('admin.sources.index')->with('status', __('Source mise à jour avec succès.'));
    }

    public function destroy(Source $source): RedirectResponse
    {
        $source->delete();

        return redirect()->route('admin.sources.index')->with('status', __('Source supprimée avec succès.'));
    }

    private function formData(): array
    {
        return [
            'banks' => Bank::query()->orderBy('name')->get(),
            'currencies' => Currency::query()->orderBy('iso_code')->get(),
        ];
    }
}
