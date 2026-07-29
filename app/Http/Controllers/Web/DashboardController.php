<?php

namespace App\Http\Controllers\Web;

use App\Domain\Reporting\Actions\BuildDashboardAction;
use App\Domain\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * UI-30: replaces the starter-kit placeholder. No Policy of its own — every
 * user with active practice access can reach /dashboard, same as before;
 * what's inside is what changes per viewer (BuildDashboardAction gates
 * each card by the same permission its target list screen requires).
 */
class DashboardController extends Controller
{
    public function index(Request $request, BuildDashboardAction $action): Response
    {
        return Inertia::render('dashboard', [
            'dashboard' => $action->execute(Tenant::current(), $request->user()),
        ]);
    }
}
