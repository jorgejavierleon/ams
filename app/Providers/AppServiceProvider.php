<?php

namespace App\Providers;

use App\Listeners\StampMarkModificationNotifiedAt;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->configureRateLimiting();

        Event::listen(NotificationSent::class, StampMarkModificationNotifiedAt::class);
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

    /**
     * The baseline every route in routes/api.php runs under: `throttleApi()` in
     * bootstrap/app.php points the api middleware group at this limiter, so no
     * mobile endpoint is unlimited. The strict per-endpoint limits (token
     * issuance, password change) sit on top of it as route middleware.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            // The api group's throttle runs before `auth:sanctum`, so the
            // request's default (web) guard has no user yet and every bearer
            // request would fall through to the IP bucket. Employees at one
            // premise punch in over the same wifi or mobile NAT, so that bucket
            // is shared by the whole premise — resolve the sanctum guard here to
            // key authenticated traffic per employee instead. RequestGuard
            // memoizes, so `auth:sanctum` does not repeat the token lookup.
            $user = $request->user('sanctum');

            if ($user !== null) {
                return Limit::perMinute(60)->by('user:'.$user->getAuthIdentifier());
            }

            // Unauthenticated traffic can only be token issuance (or a request
            // with a dead token), and it shares the premise IP. The ceiling is
            // set for a shift change where every phone re-authenticates at once
            // rather than for a single employee; credential stuffing against one
            // account is what ThrottleTokenIssuance's 5/minute is for.
            return Limit::perMinute(100)->by('ip:'.$request->ip());
        });
    }

    protected function configureMiddleware(): void
    {
        RedirectIfAuthenticated::redirectUsing(function (Request $request): string {
            // The guest middleware does not tell us which guard triggered the
            // redirect, and the dt/saas/web guards can hold separate logins in
            // the same session at once. Prefer the panel matching the request
            // path so, e.g., visiting /dt/login while authenticated on dt lands
            // on the dt dashboard instead of bouncing to another panel.
            if ($request->is('dt', 'dt/*') && Auth::guard('dt')->check()) {
                return route('dt.dashboard');
            }

            if ($request->is('saas', 'saas/*') && Auth::guard('saas')->check()) {
                return route('saas.dashboard');
            }

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

        // The mobile API returns resources at the top level (no "data" wrapper),
        // matching the flat contract the employee app expects.
        JsonResource::withoutWrapping();

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
