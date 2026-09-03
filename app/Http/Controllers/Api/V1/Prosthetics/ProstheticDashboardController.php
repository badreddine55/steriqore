<?php

namespace App\Http\Controllers\Api\V1\Prosthetics;

use App\Domain\Prosthetics\Actions\GetProstheticDashboardAction;
use App\Domain\Prosthetics\Models\ProstheticCase;
use App\Domain\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProstheticDashboardController extends Controller
{
    public function __invoke(Request $request, GetProstheticDashboardAction $action): JsonResponse
    {
        $this->authorize('viewAny', ProstheticCase::class);

        $tenant = Tenant::current() ?? $request->user()?->tenants()->first();
        $dashboard = $action->execute($tenant);
        return response()->json($dashboard);
    }
}
