<?php

namespace App\Providers;

use App\Contracts\DocumentTextExtractor;
use App\Models\IntegrationContract;
use App\Models\User;
use App\Services\DelegatedAccessResolver;
use App\Services\LocalDocumentTextExtractor;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Passport\Guards\TokenGuard;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DocumentTextExtractor::class, LocalDocumentTextExtractor::class);
        $this->app->scoped(DelegatedAccessResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        Gate::before(fn (User $user, string $ability): ?bool => app(DelegatedAccessResolver::class)->allows($user, $ability) ? true : null);
        RateLimiter::for('citizen-intake', fn (Request $request) => [Limit::perMinute(10)->by($request->ip()), Limit::perDay(50)->by($request->ip())]);
        RateLimiter::for('citizen-tracking', fn (Request $request) => Limit::perMinute(12)->by($request->ip()));
        RateLimiter::for('global-search', fn (Request $request) => Limit::perMinute(60)->by((string) ($request->user()->id ?? $request->ip())));
        RateLimiter::for('unique-value', fn (Request $request) => Limit::perMinute(120)->by((string) ($request->user()->id ?? $request->ip())));
        RateLimiter::for('certificate-verification', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
        RateLimiter::for('integration-ingest', function (Request $request): Limit {
            $guard = auth('api');
            $clientId = $guard instanceof TokenGuard ? $guard->client()?->getKey() : null;
            $contract = $request->route('contract');
            $rateLimit = $contract instanceof IntegrationContract ? $contract->rate_limit_per_minute : 60;

            return Limit::perMinute($rateLimit)->by((string) ($clientId ?? $request->ip()));
        });

        Passport::tokensCan([
            'integrations:ingest' => 'Submit source-system records through a published IDMIS integration contract.',
        ]);
        Passport::clientCredentialsTokensExpireIn(now()->addMinutes((int) config('passport.client_credentials_token_ttl_minutes', 15)));
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
