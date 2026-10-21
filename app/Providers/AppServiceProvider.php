<?php

namespace App\Providers;

use Anthropic\Client;
use App\Models\Target;
use App\Models\Vector;
use App\Services\Analysis\ClaudeFraudAnalyzer;
use App\Services\Analysis\FraudAnalyzer;
use App\Services\Analysis\GrokFraudAnalyzer;
use App\Services\Analysis\ReferenceLibrary;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
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
        $this->app->bind(FraudAnalyzer::class, fn (): FraudAnalyzer => match (config('analysis.provider')) {
            'xai', 'grok' => new GrokFraudAnalyzer(
                http: $this->app->make(HttpFactory::class),
                apiKey: $this->optionalConfig('services.xai.key'),
                model: config()->string('services.xai.model'),
                baseUrl: config()->string('services.xai.base_url'),
                reasoningEffort: $this->optionalConfig('services.xai.reasoning_effort'),
                maxOutputTokens: config()->integer('services.xai.max_output_tokens'),
                maxTurns: config()->integer('services.xai.max_turns'),
                webSearch: config()->boolean('services.xai.web_search'),
            ),
            default => new ClaudeFraudAnalyzer(
                client: new Client(apiKey: $this->optionalConfig('services.anthropic.key')),
                model: config()->string('services.anthropic.model'),
                effort: config()->string('services.anthropic.effort'),
                maxTokens: config()->integer('services.anthropic.max_tokens'),
                webSearchMaxUses: config()->integer('services.anthropic.web_search_max_uses'),
                fallbacks: config()->boolean('services.anthropic.fallbacks'),
            ),
        });

        $this->app->singleton(ReferenceLibrary::class, fn (): ReferenceLibrary => new ReferenceLibrary(
            cache: $this->app->make(Cache::class),
            promptLimit: config()->integer('analysis.references.prompt_limit'),
            storeLimit: config()->integer('analysis.references.store_limit'),
            ttl: config()->integer('analysis.references.cache_ttl'),
        ));
    }

    /**
     * A string setting that may be unset or empty, e.g. an API key.
     */
    private function optionalConfig(string $key): ?string
    {
        $value = config($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        Relation::morphMap([
            'vector' => Vector::class,
            'target' => Target::class,
        ]);

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

    /**
     * The "web" limiter runs on every web request, Fortify's included. Each
     * budget is counted per member, or per IP address for guests.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('web', function (Request $request): Limit|array {
            $user = $request->user();
            $key = $user ? 'user:'.$user->id : 'ip:'.$request->ip();
            $limit = fn (string $name): int => config()->integer("security.throttle.{$name}");

            $limits = $request->isMethodSafe()
                ? [Limit::perMinute($limit('reads_per_minute'))->by('reads|'.$key)]
                : [
                    Limit::perMinute($limit('writes_per_minute'))->by('writes|'.$key),
                    match (true) {
                        $request->routeIs('register.store') => Limit::perHour($limit('registrations_per_hour'))->by('register|'.$request->ip()),
                        $request->routeIs('password.email') => Limit::perHour($limit('password_resets_per_hour'))->by('password-reset|'.$request->ip()),
                        $request->routeIs('vectors.store', 'targets.store') && ! $user?->is_admin => Limit::perHour($limit('suggestions_per_hour'))->by('suggestions|'.$key),
                        default => null,
                    },
                ];

            $limits = array_values(array_filter($limits, fn (?Limit $limit): bool => $limit !== null && $limit->maxAttempts > 0));

            return $limits === [] ? Limit::none() : $limits;
        });
    }
}
