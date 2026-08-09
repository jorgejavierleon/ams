<?php

namespace App\Providers;

use App\Models\User;
use App\Services\LegalHourLimits;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Scoped rather than transient: the resolver memoises per date, and the
        // shift list asks for the same date once per row.
        $this->app->scoped(LegalHourLimits::class);
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

        // No event listeners are registered here on purpose. Laravel scans
        // app/Listeners and binds every handle()/__invoke() method to the event
        // it type-hints, so a manual Event::listen() for a listener in that
        // directory registers it a *second* time and the handler runs twice per
        // event. Verify with `sa event:list`; add a manual registration only for
        // a listener living outside app/Listeners.
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
     * issuance, password change, forgot password) sit on top of it as route
     * middleware.
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

        // The forgot-password endpoint (KMO-14 #5). Its response is a 204
        // whatever happened, so this limiter is the only thing standing between
        // one tap and a mailbox full of reset links — and it is also the only
        // thing an employee who taps repeatedly ever hears back from, which is
        // why the app is built to read a 429 as "wait" rather than as a failure.
        //
        // Keyed on email + IP for ThrottleTokenIssuance's reason: employees at
        // one premise share an IP, and an IP-only bucket would let one person's
        // repeated requests block their colleagues'. Three a minute is well above
        // any honest use — the broker itself will not mint a second token inside
        // 60 seconds (config/auth.php) — and low enough that the endpoint cannot
        // be used to flood one address.
        RateLimiter::for('password-reset-requests', function (Request $request) {
            $email = $request->input('email');

            // Validation has not run yet, so an absent or non-string email still
            // has to key to something rather than escape the limiter.
            return Limit::perMinute(3)->by('password-reset|'.Str::transliterate(
                Str::lower(is_string($email) ? $email : '').'|'.$request->ip()
            ));
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
