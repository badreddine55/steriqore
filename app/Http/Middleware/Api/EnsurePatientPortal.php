<?php

namespace App\Http\Middleware\Api;

use App\Domain\Identity\Models\PatientPortalAccount;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures that the request is authenticated by a valid patient portal account
 * with the 'patient-portal' token ability.
 */
class EnsurePatientPortal
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! ($user instanceof PatientPortalAccount)) {
            abort(403, 'This endpoint is restricted to authenticated patient portal accounts.');
        }

        if (! $user->tokenCan('patient-portal')) {
            abort(403, 'Missing required patient-portal permission.');
        }

        return $next($request);
    }
}
