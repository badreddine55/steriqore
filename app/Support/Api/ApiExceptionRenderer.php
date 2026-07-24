<?php

namespace App\Support\Api;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * Every /api/* error uses one envelope, per CLAUDE.md's API conventions:
 * {"error":{"code","message","details","request_id"}}. The mobile app
 * branches on `code`, never `message` — so every case below carries a
 * stable, documented code, not just an HTTP status.
 */
class ApiExceptionRenderer
{
    public static function register(Exceptions $exceptions): void
    {
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return self::toResponse($e, $request);
        });
    }

    public static function toResponse(Throwable $e, Request $request): JsonResponse
    {
        [$status, $code, $message, $details] = self::resolve($e);

        $requestId = $request->attributes->get('request_id') ?? (string) Str::uuid7();

        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => (object) $details,
                'request_id' => $requestId,
            ],
        ], $status);
    }

    /**
     * @return array{0: int, 1: string, 2: string, 3: array<string, mixed>}
     */
    private static function resolve(Throwable $e): array
    {
        return match (true) {
            $e instanceof ApiException => [$e->status, $e->errorCode, $e->getMessage(), $e->details],
            $e instanceof ValidationException => [422, 'VALIDATION_FAILED', 'The given data was invalid.', $e->errors()],
            $e instanceof AuthenticationException => [401, 'UNAUTHENTICATED', 'Authentication is required.', []],
            $e instanceof AuthorizationException => [403, 'FORBIDDEN', 'This action is unauthorized.', []],
            $e instanceof ModelNotFoundException => [404, 'NOT_FOUND', 'The requested resource was not found.', []],
            $e instanceof TooManyRequestsHttpException => [429, 'TOO_MANY_REQUESTS', 'Too many requests.', []],
            $e instanceof HttpExceptionInterface => [
                $e->getStatusCode(),
                self::genericCodeFor($e->getStatusCode()),
                $e->getMessage() !== '' ? $e->getMessage() : 'An error occurred.',
                [],
            ],
            default => [500, 'INTERNAL_ERROR', config('app.debug') ? $e->getMessage() : 'An unexpected error occurred.', []],
        };
    }

    private static function genericCodeFor(int $status): string
    {
        return match ($status) {
            404 => 'NOT_FOUND',
            405 => 'METHOD_NOT_ALLOWED',
            409 => 'CONFLICT',
            410 => 'GONE',
            default => 'HTTP_'.$status,
        };
    }
}
