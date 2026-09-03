<?php

use App\Http\Middleware\Api\AssignRequestId;
use App\Http\Middleware\Api\EnsureIdempotency;
use App\Http\Middleware\Api\SetCurrentTenant;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\Web\SetCurrentTenant as SetCurrentTenantWeb;
use App\Support\Api\ApiExceptionRenderer;
use App\Support\Security\SecurityEventRenderer;
use App\Support\Web\ApiExceptionWebRenderer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->api(prepend: [
            AssignRequestId::class,
        ]);

        $middleware->alias([
            'idempotent' => EnsureIdempotency::class,
            'tenant' => SetCurrentTenant::class,
            'tenant.web' => SetCurrentTenantWeb::class,
            'not_patient_portal' => \App\Http\Middleware\Api\EnsureNotPatientPortal::class,
            'patient_portal' => \App\Http\Middleware\Api\EnsurePatientPortal::class,
        ]);

        // Without this, Laravel's default middleware priority runs
        // SubstituteBindings (which resolves route-bound models, e.g.
        // Product $product) before this — so a BelongsToTenant-scoped
        // model's global scope would query with no current tenant bound
        // yet, and every such route would 404 (TenantScope fails closed).
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: SetCurrentTenant::class,
        );

        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: SetCurrentTenantWeb::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Registered first and deliberately: every render() callback below
        // sees the exception in registration order, and the loop stops at
        // the first one that returns a non-null response
        // (Handler::renderViaCallbacks) — this one always returns null, but
        // if it were registered after ApiExceptionRenderer/
        // ApiExceptionWebRenderer, most exceptions would already have a
        // response by the time it runs and it would simply never fire.
        SecurityEventRenderer::register($exceptions);
        ApiExceptionRenderer::register($exceptions);
        ApiExceptionWebRenderer::register($exceptions);

        // No-op until SENTRY_LARAVEL_DSN is set (S5-ANA-02) — no staging/
        // production Sentry project exists yet, see docs/open-questions.md.
        Integration::handles($exceptions);
    })->create();
