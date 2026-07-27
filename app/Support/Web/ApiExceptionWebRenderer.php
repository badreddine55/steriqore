<?php

namespace App\Support\Web;

use App\Support\Api\ApiException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Every domain Action throws ApiException for a business-rule violation
 * (CYCLE_LOAD_EMPTY, CYCLE_INVALID_TRANSITION, ...) — the exact same
 * Actions the web controllers call, not a separate set of rules. But
 * ApiExceptionRenderer only ever registers itself for `/api/*` requests
 * (CLAUDE.md's one-error-envelope convention is an API contract concern),
 * so without this, a business-rule failure on any web write action would
 * fall through to a bare, unbranded error page instead of a normal
 * validation-style "redirected back with a message" experience. This
 * mirrors how a ValidationException already behaves for web requests —
 * just for the domain's own business-rule exception type.
 */
class ApiExceptionWebRenderer
{
    public static function register(Exceptions $exceptions): void
    {
        $exceptions->render(function (ApiException $e, Request $request) {
            if ($request->is('api/*')) {
                return null;
            }

            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return redirect()->back();
        });
    }
}
