<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCurrencyRequest;
use App\Http\Requests\Admin\UpdateCurrencyRequest;
use App\Models\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CurrencyController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Currency::class, 'currency');
    }

    public function index(): View
    {
        $currencies = Currency::query()->orderBy('iso_code')->paginate(15);

        return view('admin.currencies.index', compact('currencies'));
    }

    public function create(): View
    {
        return view('admin.currencies.create');
    }

    public function store(StoreCurrencyRequest $request): RedirectResponse
    {
        Currency::query()->create($request->validated());

        return redirect()->route('admin.currencies.index')->with('status', __('Devise créée avec succès.'));
    }

    public function edit(Currency $currency): View
    {
        return view('admin.currencies.edit', compact('currency'));
    }

    public function update(UpdateCurrencyRequest $request, Currency $currency): RedirectResponse
    {
        $currency->update($request->validated());

        return redirect()->route('admin.currencies.index')->with('status', __('Devise mise à jour avec succès.'));
    }

    public function destroy(Currency $currency): RedirectResponse
    {
        $currency->delete();

        return redirect()->route('admin.currencies.index')->with('status', __('Devise supprimée avec succès.'));
    }
}
