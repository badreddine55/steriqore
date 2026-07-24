<?php

namespace App\Providers;

use App\Domain\Identity\Models\PersonalAccessToken;
use App\Support\Api\ScrambleErrorEnvelope;
use Carbon\CarbonImmutable;
use Dedoc\Scramble\Scramble;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\Sanctum;

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

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        Scramble::afterOpenApiGenerated(ScrambleErrorEnvelope::apply(...));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            $this->app->isProduction(),
        );

        Password::defaults(fn (): ?Password => $this->app->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
