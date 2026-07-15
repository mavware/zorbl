<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Sentry\State\HubInterface;

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
        $this->configureRateLimiting();
        $this->configureSentryUserScope();
    }

    /**
     * Tag every Sentry event captured while a user is authenticated with their
     * id (and email if SENTRY_SEND_DEFAULT_PII is on). No-op when Sentry isn't
     * configured.
     */
    protected function configureSentryUserScope(): void
    {
        if (! app()->bound(HubInterface::class)) {
            return;
        }

        $apply = function ($user): void {
            if ($user === null) {
                return;
            }
            try {
                app(HubInterface::class)->configureScope(function ($scope) use ($user): void {
                    $scope->setUser(array_filter([
                        'id' => (string) $user->getKey(),
                        'email' => config('sentry.send_default_pii') ? $user->email : null,
                    ]));
                });
            } catch (\Throwable) {
                // Sentry isn't configured (e.g. local without DSN) — ignore.
            }
        };

        Event::listen(Login::class, fn (Login $e) => $apply($e->user));
    }

    /**
     * Configure API rate limiters.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            $user = $request->user();
            $limit = $user?->planLimits()->apiRateLimit() ?? 30;

            return Limit::perMinute($limit)->by($user?->id ?: $request->ip());
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        Model::preventLazyLoading(! app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->uncompromised()
            : null,
        );
    }
}
