<?php

namespace App\Providers;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductCategory;
use App\Domain\Compliance\Models\AuditEvent;
use App\Domain\Equipment\Models\Device;
use App\Domain\Identity\Models\Invitation;
use App\Domain\Identity\Models\PersonalAccessToken;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Inventory\Models\Alert;
use App\Domain\Inventory\Models\StockLevel;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Labeling\Models\DluRule;
use App\Domain\Labeling\Models\Label;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\Supplier;
use App\Domain\Sterilization\Models\Cycle;
use App\Domain\Tenancy\Models\Site;
use App\Domain\Traceability\Models\Patient;
use App\Policies\AlertPolicy;
use App\Policies\AuditEventPolicy;
use App\Policies\CyclePolicy;
use App\Policies\DevicePolicy;
use App\Policies\DluRulePolicy;
use App\Policies\InvitationPolicy;
use App\Policies\LabelPolicy;
use App\Policies\PatientPolicy;
use App\Policies\ProductCategoryPolicy;
use App\Policies\ProductPolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\SitePolicy;
use App\Policies\StockLevelPolicy;
use App\Policies\StockMovementPolicy;
use App\Policies\SupplierPolicy;
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
        Gate::policy(Supplier::class, SupplierPolicy::class);
        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
        Gate::policy(StockMovement::class, StockMovementPolicy::class);
        Gate::policy(StockLevel::class, StockLevelPolicy::class);
        Gate::policy(Alert::class, AlertPolicy::class);
        Gate::policy(Device::class, DevicePolicy::class);
        Gate::policy(Cycle::class, CyclePolicy::class);
        Gate::policy(DluRule::class, DluRulePolicy::class);
        Gate::policy(Label::class, LabelPolicy::class);
        Gate::policy(Patient::class, PatientPolicy::class);
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
