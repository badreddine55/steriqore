<?php

namespace App\Providers;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductCategory;
use App\Domain\Compliance\Models\AuditEvent;
use App\Domain\Identity\Models\Invitation;
use App\Domain\Identity\Models\PersonalAccessToken;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Tenancy\Models\Site;
use App\Policies\AuditEventPolicy;
use App\Policies\InvitationPolicy;
use App\Policies\ProductCategoryPolicy;
use App\Policies\ProductPolicy;
use App\Policies\SitePolicy;
use App\Policies\TenantUserPolicy;
use App\Support\Api\ScrambleErrorEnvelope;
use Carbon\CarbonImmutable;
use Dedoc\Scramble\Scramble;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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

        // Explicit, not relying on Laravel's App\Policies\{Model}Policy
        // auto-discovery: our models live under App\Domain\..., not
        // App\Models, so the convention-based guess isn't guaranteed.
        Gate::policy(Site::class, SitePolicy::class);
        Gate::policy(Invitation::class, InvitationPolicy::class);
        Gate::policy(TenantUser::class, TenantUserPolicy::class);
        Gate::policy(AuditEvent::class, AuditEventPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(ProductCategory::class, ProductCategoryPolicy::class);
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
