<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use ReflectionClass;

abstract class TestCase extends BaseTestCase
{
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
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $this->forgetCachedGuard('sanctum');

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
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
