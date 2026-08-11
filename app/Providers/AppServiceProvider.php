<?php

namespace App\Providers;

use App\Models\Conference;
use App\Models\Presentation;
use App\Models\User;
use App\Models\Workshop;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::morphMap([
            'workshop' => Workshop::class,
            'presentation' => Presentation::class,
            'conference' => Conference::class,
            // Tipos sentinela sin modelo asociado: se guardan con event_id = 0,
            // por lo que el morphTo los resuelve como null en lugar de lanzar
            // "Class ... not found" (certificados de asistencia al evento y staff).
            'event' => Conference::class,
            'staff' => User::class,
        ]);

        $this->registerPermissionGates();

        $this->configureDefaults();
    }

    protected function registerPermissionGates(): void
    {
        Gate::before(function ($user, $ability) {
            if (! $this->isDefinedPermission($ability)) {
                return null;
            }

            if ($user->isAdmin()) {
                return true;
            }

            return $user->hasPermission($ability) ? true : false;
        });
    }

    private function isDefinedPermission(string $ability): bool
    {
        foreach (config('permissions', []) as $module) {
            if (array_key_exists($ability, $module)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }
}
