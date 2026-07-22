<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBankRequest;
use App\Http\Requests\Admin\UpdateBankRequest;
use App\Models\Bank;
use Illuminate\View\View;

class BankController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Bank::class, 'bank');
    }

    public function index(): View
    {
        $banks = Bank::query()->orderBy('name')->paginate(15);

        return view('admin.banks.index', compact('banks'));
    }

    public function create(): View
    {
        return view('admin.banks.create');
    }

    public function store(StoreBankRequest $request): \Illuminate\Http\RedirectResponse
    {
        Bank::query()->create($request->validated());

        return redirect()->route('admin.banks.index')->with('status', __('Banque créée avec succès.'));
    }

    public function edit(Bank $bank): View
    {
        return view('admin.banks.edit', compact('bank'));
    }

    public function update(UpdateBankRequest $request, Bank $bank): \Illuminate\Http\RedirectResponse
    {
        $bank->update($request->validated());

        return redirect()->route('admin.banks.index')->with('status', __('Banque mise à jour avec succès.'));
    }

    public function destroy(Bank $bank): \Illuminate\Http\RedirectResponse
    {
        $bank->delete();

        return redirect()->route('admin.banks.index')->with('status', __('Banque supprimée avec succès.'));
    }
}
