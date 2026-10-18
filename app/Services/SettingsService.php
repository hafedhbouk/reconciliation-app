<?php

namespace App\Services;

use App\Repositories\SettingsRepositoryInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Point d'entrée pour lire et écrire les paramètres de l'application.
 *
 * Exemple : app(SettingsService::class)->get('matching', 'tolerance_days', 0);
 * La valeur est gardée en cache « pour toujours » et le cache est vidé à
 * chaque modification via set(). On évite ainsi une requête SQL à chaque lecture.
 *
 * Remarque : ce service n'est appelé nulle part pour l'instant. Les règles de
 * rapprochement utilisent leurs propres tolérances (MatchingRule::criteria), et
 * SettingController modifie la table directement, donc sans vider ce cache.
 */
class SettingsService
{
    /** Laravel fournit l'implémentation choisie dans AppServiceProvider (EloquentSettingsRepository). */
    public function __construct(private SettingsRepositoryInterface $settings) {}

    /** Lit un paramètre, ou renvoie $default s'il n'existe pas. */
    public function get(string $group, string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever(
            "settings.{$group}.{$key}",
            fn () => $this->settings->find($group, $key)?->value ?? $default
        );
    }

    /** Modifie un paramètre et vide son cache, pour que la prochaine lecture voie la nouvelle valeur. */
    public function set(string $group, string $key, mixed $value): void
    {
        $this->settings->set($group, $key, $value);
        Cache::forget("settings.{$group}.{$key}");
    }
}
