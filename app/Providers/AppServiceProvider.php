<?php

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $this->configureDefaults();
        $this->configureMiddleware();
        $this->configureAuthorization();
    }

    /**
     * Grant every ability to admins. Running before all policies, this lets the
     * admin role act as a super admin so individual policies never need to
     * special-case it.
     *
     * @see https://spatie.be/docs/laravel-permission/v8/basic-usage/super-admin
     */
    protected function configureAuthorization(): void
    {
        Gate::before(fn (User $user): ?bool => $user->hasRole('admin') ? true : null);
    }

    protected function configureMiddleware(): void
    {
        RedirectIfAuthenticated::redirectUsing(function (Request $request): string {
            if (Auth::guard('saas')->check()) {
                return route('saas.dashboard');
            }

            if (Auth::guard('dt')->check()) {
                return route('dt.dashboard');
            }

            return route('dashboard');
        });
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
            : null,
        );
    }
}
