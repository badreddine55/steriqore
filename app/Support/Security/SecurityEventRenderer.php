<?php

namespace App\Support\Security;

use App\Domain\Compliance\Actions\RecordSecurityEventAction;
use App\Domain\Compliance\Enums\SecurityEventType;
use App\Domain\Tenancy\Models\Tenant;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Tenancy\TenantScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * The dedicated security channel REC-01 promises but never had: every
 * authorization failure, cross-tenant resolution attempt, and RLS policy
 * rejection, recorded to security_events (never audit_events — see that
 * migration's docblock for why). Purely observational — every branch below
 * returns null, so the actual response (403, 404, the normal 500 page) is
 * always decided by ApiExceptionRenderer/ApiExceptionWebRenderer/Laravel's
 * own defaults exactly as before; this only ever adds a side effect.
 *
 * Registered FIRST in bootstrap/app.php, deliberately: Handler::render()
 * calls prepareException() once up front — which is where
 * AuthorizationException becomes AccessDeniedHttpException/HttpException
 * and ModelNotFoundException becomes NotFoundHttpException — so every
 * render() callback, including this one, only ever sees the CONVERTED
 * type; the original is reachable only via ->getPrevious(). Separately,
 * Handler::renderViaCallbacks() stops at the first callback that returns
 * non-null, so unless this one runs before ApiExceptionRenderer/
 * ApiExceptionWebRenderer, it would never even be invoked for most of
 * them.
 *
 * A cross-tenant resolution attempt specifically means: the id in the URL
 * resolves to a REAL row in a DIFFERENT tenant, not just any 404. A wholly
 * made-up id is normal 404 noise, not a security event — recording every
 * ModelNotFoundException indiscriminately would drown the signal the
 * brief asked for ("a rate spike on this channel is the signal that
 * matters") in typos and dead links.
 */
class SecurityEventRenderer
{
    public static function register(Exceptions $exceptions): void
    {
        $exceptions->render(function (Throwable $e, Request $request) {
            self::record($e, $request);

            return null;
        });
    }

    private static function record(Throwable $e, Request $request): void
    {
        try {
            if (self::isAuthorizationFailure($e)) {
                self::recordAuthorizationFailure($request);

                return;
            }

            if ($e instanceof HttpExceptionInterface
                && $e->getStatusCode() === 404
                && $e->getPrevious() instanceof ModelNotFoundException) {
                self::recordCrossTenantAttemptIfReal($e->getPrevious());

                return;
            }

            if (self::isRlsPolicyRejection($e)) {
                app(RecordSecurityEventAction::class)->execute(SecurityEventType::RlsPolicyRejection);
            }
        } catch (Throwable $recordingFailure) {
            // Recording a security event must never itself break the
            // response being rendered for the original exception.
            report($recordingFailure);
        }
    }

    private static function isAuthorizationFailure(Throwable $e): bool
    {
        return $e instanceof AuthorizationException
            || ($e instanceof HttpExceptionInterface && $e->getStatusCode() === 403);
    }

    private static function recordAuthorizationFailure(Request $request): void
    {
        $subject = collect($request->route()?->parameters() ?? [])
            ->first(fn ($value) => $value instanceof Model);

        app(RecordSecurityEventAction::class)->execute(
            type: SecurityEventType::AuthorizationFailure,
            subjectType: $subject?->getMorphClass(),
            subjectId: $subject?->getKey(),
        );
    }

    /**
     * @param  ModelNotFoundException<Model>  $e
     */
    private static function recordCrossTenantAttemptIfReal(ModelNotFoundException $e): void
    {
        $modelClass = $e->getModel();

        if (! is_subclass_of($modelClass, Model::class) || ! self::usesBelongsToTenant($modelClass)) {
            return;
        }

        $tenantClaimed = Tenant::current();

        foreach ($e->getIds() as $id) {
            $record = $modelClass::on('pgsql_admin')
                ->withoutGlobalScope(TenantScope::class)
                ->find($id);

            if (! $record instanceof Model) {
                continue;
            }

            $recordTenantId = $record->getAttribute('tenant_id');

            if ($recordTenantId === null) {
                continue;
            }

            if ($tenantClaimed !== null && $recordTenantId === $tenantClaimed->id) {
                // Genuinely not found within the caller's own tenant
                // (soft-deleted, a different scope excluded it, ...) —
                // not a cross-tenant probe.
                continue;
            }

            $tenantActual = Tenant::find($recordTenantId);

            if (! $tenantActual instanceof Tenant) {
                continue;
            }

            app(RecordSecurityEventAction::class)->execute(
                type: SecurityEventType::CrossTenantResolutionAttempt,
                tenantActual: $tenantActual,
                subjectType: $modelClass,
                subjectId: (string) $id,
            );
        }
    }

    private static function isRlsPolicyRejection(Throwable $e): bool
    {
        return $e instanceof QueryException
            && str_contains($e->getMessage(), 'row-level security policy');
    }

    /**
     * @param  class-string  $modelClass
     */
    private static function usesBelongsToTenant(string $modelClass): bool
    {
        return in_array(BelongsToTenant::class, class_uses_recursive($modelClass), true);
    }
}
