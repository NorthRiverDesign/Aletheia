<?php

namespace App\Providers;

use Anthropic\Client;
use App\Models\Target;
use App\Models\Vector;
use App\Services\Analysis\ClaudeFraudAnalyzer;
use App\Services\Analysis\FraudAnalyzer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(FraudAnalyzer::class, fn (): FraudAnalyzer => new ClaudeFraudAnalyzer(
            client: new Client(apiKey: config()->string('services.anthropic.key', '') ?: null),
            model: config()->string('services.anthropic.model'),
            effort: config()->string('services.anthropic.effort'),
            maxTokens: config()->integer('services.anthropic.max_tokens'),
            webSearchMaxUses: config()->integer('services.anthropic.web_search_max_uses'),
            fallbacks: config()->boolean('services.anthropic.fallbacks'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
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
}
