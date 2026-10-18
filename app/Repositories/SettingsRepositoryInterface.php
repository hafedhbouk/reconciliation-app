<?php

namespace App\Repositories;

use App\Models\Setting;
use Illuminate\Support\Collection;

/**
 * Contrat d'accès aux paramètres enregistrés.
 *
 * Patron « Repository » : le reste du code (SettingsService) ne sait pas OÙ
 * sont stockés les paramètres ; il passe par ces méthodes. L'implémentation
 * actuelle (EloquentSettingsRepository) lit la table settings.
 * Le lien interface -> implémentation est déclaré dans AppServiceProvider::register().
 */
interface SettingsRepositoryInterface
{
    /** Tous les paramètres d'un groupe. */
    public function allForGroup(string $group): Collection;

    /** Un paramètre précis, ou null s'il n'existe pas. */
    public function find(string $group, string $key): ?Setting;

    /** Crée ou met à jour un paramètre. */
    public function set(string $group, string $key, mixed $value): Setting;
}
