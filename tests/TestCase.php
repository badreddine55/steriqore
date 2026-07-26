<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Laravel\Fortify\Features;
use ReflectionClass;

abstract class TestCase extends BaseTestCase
{
    /**
     * The 'array' cache store (phpunit.xml's CACHE_STORE) survives across
     * every test in the same process — including the request-count keys
     * S5-ANA-01's 'api'/'public-api' rate limiters write. Without this,
     * tests would trip each other's throttle after enough shared-IP/no-op
     * requests accumulate across the suite, not because any single test
     * actually exceeded a limit.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    /**
     * Illuminate\Auth\RequestGuard caches the resolved user on itself once
     * per instance, and Illuminate\Auth\AuthManager caches guard instances
     * by name — both survive across multiple `$this->withToken(...)->json()`
     * calls within a single test (the container isn't rebuilt between them).
     * A second simulated request with a *different* (or freshly revoked)
     * Sanctum token would otherwise silently authenticate as whichever user
     * the "sanctum" guard resolved first. Real requests never hit this —
     * each is a fresh PHP process — it's purely a test-simulation artifact.
     *
     * Only the "sanctum" guard is dropped: AuthManager::forgetGuards() would
     * also drop the "web" session guard, breaking every actingAs()-based
     * Inertia test (actingAs() pre-sets the user directly on that cached
     * guard instance, before the request is even dispatched).
     *
     * A second, related trap: Illuminate\Auth\Middleware\Authenticate calls
     * Auth::shouldUse($guard) on every successful check — so a prior
     * `auth:sanctum`-protected request (any web/API test that mints a
     * token via the mobile login endpoint to set up fixtures, then also
     * exercises a web/Inertia route in the same test) silently makes
     * "sanctum" the default guard for the rest of the test. Resetting the
     * default back to "web" here — the only guard this app's web routes
     * ever use — before every simulated request closes that off the same
     * way forgetCachedGuard() closes the first, for any code that resolves
     * the user via a guard-less $request->user()/auth()->user() call.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $this->forgetCachedGuard('sanctum');
        $this->app['auth']->shouldUse('web');

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    /**
     * actingAs($user) with no driver resolves the guard to set the user on
     * via the *current* default at the moment it's called — which the
     * Authenticate-middleware trap above can have already left pointing at
     * "sanctum" before this even runs (call()'s own reset only takes
     * effect on the *next* dispatched request, not retroactively). Every
     * web/Inertia test in this app authenticates via the session guard, so
     * defaulting explicitly to "web" here — rather than trusting whatever
     * the last request happened to leave as current — is correct for all
     * of them and avoids the same silent-wrong-user failure mode.
     */
    public function actingAs(Authenticatable $user, $guard = null)
    {
        return parent::actingAs($user, $guard ?? 'web');
    }

    private function forgetCachedGuard(string $name): void
    {
        $auth = $this->app['auth'];
        $property = (new ReflectionClass($auth))->getProperty('guards');
        $guards = $property->getValue($auth);
        unset($guards[$name]);
        $property->setValue($auth, $guards);
    }
}
