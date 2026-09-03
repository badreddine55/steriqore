<?php

namespace App\Http\Middleware\Api;

use App\Domain\Identity\Models\PatientPortalAccount;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deny-by-default gate: Ensures that a token issued to a patient portal account
 * can never access any staff, operational, or clinical endpoint.
 */
class EnsureNotPatientPortal
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() instanceof PatientPortalAccount) {
            abort(403, 'Patient portal accounts cannot access clinical or operational endpoints.');
        }

        return $next($request);
    }
}
