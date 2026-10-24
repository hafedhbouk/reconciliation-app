<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBankRequest;
use App\Http\Requests\Admin\UpdateBankRequest;
use App\Models\Bank;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Gestion des banques (Administration > Banques).
 *
 * C'est l'exemple type d'un « CRUD » (Create, Read, Update, Delete) Laravel.
 * Les contrôleurs CurrencyController et SourceController
 * suivent exactement le même modèle.
 * (Les notions de contrôleur sont expliquées dans ImportController.php.)
 */
class BankController extends Controller
{
    /** Chaque méthode vérifie le droit correspondant dans BankPolicy (voir app/Policies). */
    public function __construct()
    {
        $this->authorizeResource(Bank::class, 'bank');
    }

    /** Liste des banques, triées par nom, 15 par page. */
    public function index(): View
    {
        $banks = Bank::query()->orderBy('name')->paginate(15);

        return view('admin.banks.index', compact('banks'));
    }

    /** Formulaire vide de création. */
    public function create(): View
    {
        return view('admin.banks.create');
    }

    /**
     * Enregistre une nouvelle banque. StoreBankRequest a déjà validé les champs ;
     * $request->validated() ne renvoie QUE les champs validés.
     */
    public function store(StoreBankRequest $request): RedirectResponse
    {
        Bank::query()->create($request->validated());

        return redirect()->route('admin.banks.index')->with('status', __('Banque créée avec succès.'));
    }

    /** Formulaire de modification, prérempli avec la banque de l'URL. */
    public function edit(Bank $bank): View
    {
        return view('admin.banks.edit', compact('bank'));
    }

    /** Enregistre les modifications. */
    public function update(UpdateBankRequest $request, Bank $bank): RedirectResponse
    {
        $bank->update($request->validated());

        return redirect()->route('admin.banks.index')->with('status', __('Banque mise à jour avec succès.'));
    }

    /** Suppression (soft delete : la ligne reste en base avec une date deleted_at). */
    public function destroy(Bank $bank): RedirectResponse
    {
        $bank->delete();

        return redirect()->route('admin.banks.index')->with('status', __('Banque supprimée avec succès.'));
    }
}
