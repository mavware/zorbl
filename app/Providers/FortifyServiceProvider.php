<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\LoginResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
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
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn () => view('pages::auth.login'));
        Fortify::verifyEmailView(fn () => view('pages::auth.verify-email'));
        Fortify::twoFactorChallengeView(fn () => view('pages::auth.two-factor-challenge'));
        Fortify::confirmPasswordView(fn () => view('pages::auth.confirm-password'));
        Fortify::registerView(fn () => view('pages::auth.register'));
        Fortify::resetPasswordView(fn () => view('pages::auth.reset-password'));
        Fortify::requestPasswordResetLinkView(fn () => view('pages::auth.forgot-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        // Per-IP throttle for new-account creation. 10/min comfortably handles
        // a user retrying after a typo while stopping a script that tries
        // hundreds of usernames a minute.
        RateLimiter::for('register-attempts', function (Request $request) {
            return Limit::perMinute(10)->by((string) $request->ip());
        });

        // Password-reset link requests + reset-submissions. Combined limiter
        // since they're the same attack surface (account-takeover precursor).
        RateLimiter::for('password-reset-requests', function (Request $request) {
            $email = $request->input('email', '');
            $key = $email !== ''
                ? Str::transliterate(Str::lower((string) $email).'|'.$request->ip())
                : (string) $request->ip();

            return Limit::perMinute(5)->by($key);
        });

        // Verification email resends. Two per minute is plenty for a real
        // user who didn't get the first one.
        RateLimiter::for('verification-resend', function (Request $request) {
            $user = $request->user();
            $key = $user !== null ? 'user|'.$user->getKey() : (string) $request->ip();

            return Limit::perMinute(2)->by($key);
        });

        // Google OAuth callback. Each round-trip costs us a Socialite HTTP
        // request to Google, so we want a tight cap on garbage callbacks.
        RateLimiter::for('oauth-callback', function (Request $request) {
            return Limit::perMinute(20)->by((string) $request->ip());
        });
    }
}
