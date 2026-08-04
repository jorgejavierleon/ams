<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
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
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        // The reset email, in Spanish.
        //
        // Laravel's own copy is assembled from `Lang::get('Reset your
        // password')` and four more English sentences used as their own
        // translation keys, and this app has no `lang/es.json` — so it sends
        // English regardless of `app.locale` being `es`. Fine while the only
        // people resetting a password are console administrators, and not fine
        // once the employee mobile app offers a forgot-password link (KOL-9):
        // Res. 38 Art. 5 requires Spanish for what an employee is asked to
        // read, and the app's confirmation screen sends them straight here.
        //
        // Through this callback rather than a `lang/es.json`, because a JSON
        // file is keyed on the English sentence: when a framework upgrade
        // rewords one, the key stops matching and that line silently reverts to
        // English with nothing to fail. The subject above was `Reset Password
        // Notification` in older Laravel, so this is not hypothetical.
        //
        // Through this callback rather than a notification of our own, because
        // the broker keeps sending the framework's class — nothing else in the
        // app has to learn a new name.
        ResetPassword::toMailUsing(
            fn (CanResetPassword $notifiable, string $token): MailMessage => (new MailMessage)
                ->subject(__('mail.password_reset.subject'))
                ->markdown('mail.password-reset', [
                    // The console's existing reset page, which is what the
                    // phone's browser opens. Built from the named route rather
                    // than a hand-written path so it cannot drift from
                    // routes/web.php, and relative-then-`url()` like the
                    // framework's own, so it honours APP_URL behind a proxy.
                    'url' => url(route('password.reset', [
                        'token' => $token,
                        'email' => $notifiable->getEmailForPasswordReset(),
                    ], false)),
                    // Read from the broker's configuration rather than written
                    // into the copy: an email naming a different number from the
                    // one the token honours is worse than one naming none.
                    'minutes' => (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire'),
                ])
        );
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/verify-email', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

    }
}
