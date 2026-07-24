<?php

namespace App\Http\Middleware\Api;

use App\Models\User;
use App\Support\Api\ApiException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idempotency is mandatory on every mutation (CLAUDE.md API conventions):
 * the client sends `Idempotency-Key`, the server stores key + request hash +
 * response for 24h and replays it verbatim on repeat, so a retried request
 * (dropped connection, doubled tap) can never double-apply. Cache store is
 * whatever CACHE_STORE resolves to — Redis in dev/staging/prod, array in
 * tests — the key/hash/replay contract is identical either way.
 */
class EnsureIdempotency
{
    private const TTL_SECONDS = 86400;

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (! $key) {
            throw new ApiException(
                errorCode: 'IDEMPOTENCY_KEY_REQUIRED',
                message: 'This request must include an Idempotency-Key header.',
                status: 400,
            );
        }

        $requestHash = hash('sha256', $request->method().'|'.$request->path().'|'.$request->getContent());
        $cacheKey = $this->cacheKey($request, $key);

        $stored = Cache::get($cacheKey);

        if ($stored !== null) {
            if ($stored['hash'] !== $requestHash) {
                throw new ApiException(
                    errorCode: 'IDEMPOTENCY_KEY_REUSED',
                    message: 'This Idempotency-Key was already used with a different request.',
                    status: 409,
                );
            }

            return response()->json($stored['body'], $stored['status']);
        }

        $response = $next($request);

        Cache::put($cacheKey, [
            'hash' => $requestHash,
            'status' => $response->getStatusCode(),
            'body' => json_decode($response->getContent(), true),
        ], self::TTL_SECONDS);

        return $response;
    }

    private function cacheKey(Request $request, string $idempotencyKey): string
    {
        $user = $request->user();
        $scope = $user instanceof User ? $user->id : 'guest';

        return "steriqore:idem:{$scope}:{$idempotencyKey}";
    }
}
