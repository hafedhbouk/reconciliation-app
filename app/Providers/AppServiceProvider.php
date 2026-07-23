<?php

namespace App\Providers;

use App\Listeners\LogFailedLogin;
use App\Listeners\LogSuccessfulLogin;
use App\Listeners\LogSuccessfulLogout;
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

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SettingsRepositoryInterface::class, EloquentSettingsRepository::class);
        $this->app->singleton(TransformRegistry::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Blueprint::macro('userstamps', function () {
            /** @var Blueprint $this */
            $this->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $this->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Gate::before(function ($user) {
            return $user->hasRole('super-admin') ? true : null;
        });

        Gate::policy(Role::class, \App\Policies\RolePolicy::class);
        Gate::policy(Permission::class, \App\Policies\RolePolicy::class);

        Event::listen(Login::class, LogSuccessfulLogin::class);
        Event::listen(Logout::class, LogSuccessfulLogout::class);
        Event::listen(Failed::class, LogFailedLogin::class);

        // Applied to matching-rule triggers and every export route -- all
        // dispatch expensive queued work or CPU/memory-heavy rendering
        // (confirmed expensive: Phase 4's real XLSX/PDF memory-exhaustion bug).
        RateLimiter::for('expensive-actions', fn (Request $request) => Limit::perMinute(10)->by(
            $request->user()?->id ?: $request->ip()
        ));

        Password::defaults(fn () => Password::min(8)->mixedCase()->numbers()->symbols());
    }
}
