<?php

namespace App\Providers;

use App\Listeners\LogFailedLogin;
use App\Listeners\LogSuccessfulLogin;
use App\Listeners\LogSuccessfulLogout;
use App\Models\MatchingExport;
use App\Policies\MatchingExportPolicy;
use App\Policies\RolePolicy;
use App\Repositories\EloquentSettingsRepository;
use App\Repositories\SettingsRepositoryInterface;
use App\Services\Import\TransformRegistry;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Configuration générale de l'application, exécutée au démarrage de CHAQUE requête.
 *
 * Un « Service Provider » sert à brancher les éléments entre eux :
 * - register() : dire à Laravel comment construire certains objets ;
 * - boot()     : régler le reste une fois tout chargé (droits, événements, limites…).
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Quand un code demande l'interface SettingsRepositoryInterface, fournir
        // un EloquentSettingsRepository. On pourrait changer d'implémentation
        // ici sans toucher au code qui l'utilise.
        $this->app->bind(SettingsRepositoryInterface::class, EloquentSettingsRepository::class);
        // « singleton » : un seul TransformRegistry partagé par toute l'application.
        $this->app->singleton(TransformRegistry::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Raccourci pour les migrations : $table->userstamps() crée les colonnes
        // created_by et updated_by, reliées à la table users.
        Blueprint::macro('userstamps', function () {
            /** @var Blueprint $this */
            $this->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $this->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
        });

        // Vérification faite AVANT toutes les Policies : le rôle super-admin a tous les droits.
        // Renvoyer null signifie « pas de décision ici, continuer avec la Policy normale ».
        Gate::before(function ($user) {
            return $user->hasRole('super-admin') ? true : null;
        });

        // Associations Policy <-> modèle que Laravel ne peut pas deviner par le nom
        // (les modèles Role et Permission viennent du paquet spatie).
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, RolePolicy::class);
        Gate::policy(MatchingExport::class, MatchingExportPolicy::class);

        // Écoute des événements de connexion, pour les inscrire dans le journal d'audit (dossier Listeners).
        Event::listen(Login::class, LogSuccessfulLogin::class);
        Event::listen(Logout::class, LogSuccessfulLogout::class);
        Event::listen(Failed::class, LogFailedLogin::class);

        // Applied to matching-rule triggers and every export route -- all
        // dispatch expensive queued work or CPU/memory-heavy rendering
        // (confirmed expensive: Phase 4's real XLSX/PDF memory-exhaustion bug).
        // (Au maximum 10 actions coûteuses par minute et par utilisateur ; ce
        // limiteur est appliqué aux routes concernées dans routes/web.php.)
        RateLimiter::for('expensive-actions', fn (Request $request) => Limit::perMinute(10)->by(
            $request->user()?->id ?: $request->ip()
        ));

        // Règles des mots de passe : 8 caractères minimum, majuscules et minuscules, chiffres et symboles.
        Password::defaults(fn () => Password::min(8)->mixedCase()->numbers()->symbols());
    }
}
