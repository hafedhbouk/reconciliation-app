<?php

namespace App\Repositories;

use App\Models\Setting;
use Illuminate\Support\Collection;

/**
 * Implémentation de SettingsRepositoryInterface qui lit et écrit la table
 * settings via le modèle Eloquent Setting (voir l'interface pour le principe).
 */
class EloquentSettingsRepository implements SettingsRepositoryInterface
{
    public function allForGroup(string $group): Collection
    {
        return Setting::query()->where('group', $group)->get();
    }

    public function find(string $group, string $key): ?Setting
    {
        // first() renvoie la première ligne trouvée, ou null.
        return Setting::query()->where('group', $group)->where('key', $key)->first();
    }

    public function set(string $group, string $key, mixed $value): Setting
    {
        return Setting::query()->updateOrCreate(
            ['group' => $group, 'key' => $key], // recherche
            ['value' => $value]                 // valeur à enregistrer
        );
    }
}
