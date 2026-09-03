<?php

namespace App\Domain\Prosthetics\Actions;

use App\Domain\Prosthetics\Data\ProstheticCaseData;
use App\Domain\Prosthetics\Data\ProstheticDashboardData;
use App\Domain\Prosthetics\Models\ProstheticCase;
use App\Domain\Tenancy\Models\Tenant;
use Carbon\Carbon;

class GetProstheticDashboardAction
{
    public function execute(Tenant $tenant): ProstheticDashboardData
    {
        $today = Carbon::today();
        $endOfWeek = Carbon::today()->endOfWeek();

        $cases = ProstheticCase::where('tenant_id', $tenant->id)->get();

        $activeCount = $cases->filter(fn ($c) => !in_array($c->status, ['placed', 'cancelled', 'remade']))->count();
        $atLabCount = $cases->where('status', 'sent_to_lab')->count();
        $returnedCount = $cases->where('status', 'received_at_practice')->count();
        
        $waitingPlacementCases = $cases->filter(function ($c) {
            return $c->returned_from_lab_date && !$c->actual_placement_date && $c->status !== 'placed';
        });
        $waitingPlacementCount = $waitingPlacementCases->count();

        $placementsToday = $cases->filter(function ($c) use ($today) {
            return $c->planned_placement_date && Carbon::parse($c->planned_placement_date)->isToday() && !$c->actual_placement_date;
        })->count();

        $placementsThisWeek = $cases->filter(function ($c) use ($today, $endOfWeek) {
            if (!$c->planned_placement_date || $c->actual_placement_date) return false;
            $dt = Carbon::parse($c->planned_placement_date);
            return $dt->gte($today) && $dt->lte($endOfWeek);
        })->count();

        $paymentsToVerify = $cases->filter(function ($c) {
            return ($c->deposit_requested && !$c->deposit_received) || (!$c->final_payment_completed && ($c->total_amount - $c->deposit_amount) > 0);
        })->count();

        $relanceCount = $waitingPlacementCases->filter(function ($c) {
            $days = (int) Carbon::parse($c->returned_from_lab_date)->diffInDays(now());
            return $days >= 8;
        })->count();

        $recentReturned = $waitingPlacementCases->sortByDesc('returned_from_lab_date')->take(5)->map(fn ($c) => ProstheticCaseData::fromModel($c)->toArray())->values()->all();
        $upcomingPlacements = $cases->filter(fn ($c) => $c->planned_placement_date && !$c->actual_placement_date)->sortBy('planned_placement_date')->take(5)->map(fn ($c) => ProstheticCaseData::fromModel($c)->toArray())->values()->all();

        return new ProstheticDashboardData(
            active_cases: $activeCount,
            active_cases_delta: '+2 vs hier',
            at_laboratory: $atLabCount,
            at_laboratory_delta: '+1 vs hier',
            returned_to_practice: $returnedCount,
            returned_to_practice_delta: '+1 vs hier',
            waiting_for_placement: $waitingPlacementCount,
            waiting_for_placement_delta: '+1 vs hier',
            placements_today: $placementsToday,
            placements_this_week: $placementsThisWeek,
            payments_to_verify: $paymentsToVerify,
            payments_to_verify_delta: '-1 vs hier',
            relance_count: $relanceCount,
            upcoming_placements_count: $upcomingPlacements ? count($upcomingPlacements) : 0,
            recent_returned_cases: $recentReturned,
            upcoming_placements: $upcomingPlacements,
        );
    }
}
