<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Paramètres de l'application (Administration > Paramètres).
 * On ne crée ni ne supprime de paramètre ici : ils viennent de SettingSeeder ;
 * l'écran permet seulement de modifier leur valeur.
 */
class SettingController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Setting::class, 'setting');
    }

    public function index(): View
    {
        $settings = Setting::query()->orderBy('group')->orderBy('key')->get()->groupBy('group');

        return view('admin.settings.index', compact('settings'));
    }

    public function edit(Setting $setting): View
    {
        return view('admin.settings.edit', compact('setting'));
    }

    public function update(UpdateSettingRequest $request, Setting $setting): RedirectResponse
    {
        $setting->update(['value' => $this->castValue($setting->type, $request->validated('value'))]);

        return redirect()->route('admin.settings.index')->with('status', __('Paramètre mis à jour avec succès.'));
    }

    /**
     * Un formulaire envoie toujours du texte : on convertit la valeur selon le type
     * du paramètre ('5' -> 5 pour un entier, 'on'/'1' -> true pour un booléen).
     */
    private function castValue(string $type, mixed $value): mixed
    {
        return match ($type) {
            'integer' => (int) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default => $value,
        };
    }
}
