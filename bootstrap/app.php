<?php

use App\Http\Middleware\Api\AssignRequestId;
use App\Http\Middleware\Api\EnsureIdempotency;
use App\Http\Middleware\Api\SetCurrentTenant;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Support\Api\ApiExceptionRenderer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

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
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        ApiExceptionRenderer::register($exceptions);
    })->create();
