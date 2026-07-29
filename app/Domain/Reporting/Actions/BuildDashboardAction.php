<?php

namespace App\Domain\Reporting\Actions;

use App\Domain\Compliance\Models\NonConformity;
use App\Domain\Equipment\Enums\DeviceKind;
use App\Domain\Equipment\Models\Device;
use App\Domain\Equipment\Models\DeviceProgram;
use App\Domain\Inventory\Enums\AlertState;
use App\Domain\Inventory\Enums\AlertType;
use App\Domain\Inventory\Models\Alert;
use App\Domain\Labeling\Enums\LabelStatus;
use App\Domain\Labeling\Models\Label;
use App\Domain\Reporting\Data\AttentionCardData;
use App\Domain\Reporting\Data\DashboardData;
use App\Domain\Reporting\Data\OnboardingStepData;
use App\Domain\Reporting\Data\RecentProcedureData;
use App\Domain\Reporting\Data\TodayCycleData;
use App\Domain\Sterilization\Enums\CycleStatus;
use App\Domain\Sterilization\Models\Cycle;
use App\Domain\Tenancy\Models\Site;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Traceability\Models\LabelUsage;
use App\Models\User;
use App\Support\Dashboard\DashboardCache;
use Throwable;

/**
 * UI-30: "what needs me today," not an analytics page. Every count here
 * either IS or mirrors exactly the query its target list screen filters
 * with (see the `missing_non_conformity`/`resolved`/`overdue` filters
 * added to CycleController/NonConformityController/LabelController, and
 * the Alert type/state filters AlertController already had) — never a
 * separately-maintained aggregate that could drift from what clicking
 * through actually shows.
 *
 * The tenant-wide facts (attention counts, today's cycles, onboarding
 * state) are cached and identical for every viewer; per-viewer
 * personalization (which cards this user's permissions allow, their own
 * recent procedures) is applied after the cache read, never inside it, so
 * one tenant-scoped cache entry serves every role.
 *
 * Each attention card's query is its own try/catch: a broken query in one
 * domain marks just that card `error: true` (frontend renders its error
 * state) rather than throwing and blanking the whole page — the same
 * "one failing query degrades that card" isolation applies to today's
 * cycles and the practitioner's recent procedures as their own units.
 *
 * "Control tests overdue/due today" is deliberately not a card here: no
 * per-tenant test frequency/tolerance rule exists to determine "overdue"
 * against (OQ-7) — building one would mean inventing a compliance rule,
 * which the per-task contract forbids. Skipped by explicit user decision,
 * same precedent as UI-17 (inventory counts).
 */
class BuildDashboardAction
{
    public function execute(Tenant $tenant, User $user): DashboardData
    {
        $snapshot = DashboardCache::remember($tenant, fn () => $this->buildSnapshot($tenant));

        if ($snapshot['is_new_practice']) {
            return new DashboardData(
                is_new_practice: true,
                onboarding_steps: $this->onboardingSteps($snapshot['onboarding'], $user),
                attention_cards: [],
                today_cycles: [],
                today_cycles_error: false,
                my_recent_procedures: [],
                my_recent_procedures_error: false,
            );
        }

        $attentionCards = collect($snapshot['attention_cards'])
            ->filter(fn (array $card) => $user->can($card['permission']))
            ->map(fn (array $card) => new AttentionCardData($card['key'], $card['label'], $card['count'], $card['error']))
            ->values()
            ->all();

        $todayCycles = $user->can('cycles.view')
            ? array_map(fn (array $row) => new TodayCycleData(...$row), $snapshot['today_cycles']['rows'])
            : [];

        [$recentProcedures, $recentProceduresError] = $this->myRecentProcedures($user);

        return new DashboardData(
            is_new_practice: false,
            onboarding_steps: [],
            attention_cards: $attentionCards,
            today_cycles: $todayCycles,
            today_cycles_error: $snapshot['today_cycles']['error'],
            my_recent_procedures: $recentProcedures,
            my_recent_procedures_error: $recentProceduresError,
        );
    }

    /**
     * @return array{is_new_practice: true, onboarding: array{site: bool, autoclave: bool, program: bool, cycle: bool}}|array{
     *     is_new_practice: false,
     *     attention_cards: array<int, array{key: string, label: string, permission: string, count: int, error: bool}>,
     *     today_cycles: array{rows: array<int, array{id: string, cycle_number: int, device_name: string, status: string, release_decision: string|null}>, error: bool},
     * }
     */
    private function buildSnapshot(Tenant $tenant): array
    {
        $isNewPractice = ! Device::query()->exists() && ! Cycle::query()->exists();

        if ($isNewPractice) {
            return [
                'is_new_practice' => true,
                'onboarding' => [
                    'site' => Site::query()->exists(),
                    'autoclave' => Device::where('kind', DeviceKind::Autoclave)->exists(),
                    'program' => DeviceProgram::query()->exists(),
                    'cycle' => false,
                ],
            ];
        }

        return [
            'is_new_practice' => false,
            'attention_cards' => $this->buildAttentionCards($tenant),
            'today_cycles' => $this->buildTodayCycles($tenant),
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, permission: string, count: int, error: bool}>
     */
    private function buildAttentionCards(Tenant $tenant): array
    {
        $timezone = $tenant->timezone;

        $definitions = [
            [
                'key' => 'cycles_awaiting_release',
                'label' => 'Cycles awaiting release',
                'permission' => 'cycles.view',
                'query' => fn () => Cycle::where('status', CycleStatus::AwaitingRelease)->count(),
            ],
            [
                'key' => 'cycles_rejected_no_non_conformity',
                'label' => 'Rejected cycles without a non-conformity',
                'permission' => 'cycles.view',
                'query' => fn () => Cycle::where('status', CycleStatus::Rejected)->whereDoesntHave('nonConformities')->count(),
            ],
            [
                'key' => 'open_non_conformities',
                'label' => 'Open non-conformities & recalls',
                'permission' => 'non_conformities.view',
                'query' => fn () => NonConformity::whereNull('resolved_at')->count(),
            ],
            [
                'key' => 'batches_expired_in_stock',
                'label' => 'Batches expired, still in stock',
                'permission' => 'alerts.view',
                'query' => fn () => Alert::where('type', AlertType::Expired)->where('state', AlertState::Open)->count(),
            ],
            [
                'key' => 'batches_near_expiry',
                'label' => 'Batches expiring soon',
                'permission' => 'alerts.view',
                'query' => fn () => Alert::where('type', AlertType::NearExpiry)->where('state', AlertState::Open)->count(),
            ],
            [
                'key' => 'products_low_stock',
                'label' => 'Products below their low-stock threshold',
                'permission' => 'alerts.view',
                'query' => fn () => Alert::where('type', AlertType::LowStock)->where('state', AlertState::Open)->count(),
            ],
            [
                'key' => 'labels_past_dlu_unused',
                'label' => 'Labels past use-by, never used',
                'permission' => 'labels.view',
                'query' => fn () => Label::whereIn('status', [LabelStatus::Created, LabelStatus::Printed])
                    ->where('use_by_date', '<', now($timezone)->toDateString())
                    ->count(),
            ],
        ];

        return array_map(function (array $def) {
            try {
                return [
                    'key' => $def['key'],
                    'label' => $def['label'],
                    'permission' => $def['permission'],
                    'count' => $def['query'](),
                    'error' => false,
                ];
            } catch (Throwable $e) {
                report($e);

                return [
                    'key' => $def['key'],
                    'label' => $def['label'],
                    'permission' => $def['permission'],
                    'count' => 0,
                    'error' => true,
                ];
            }
        }, $definitions);
    }

    /**
     * @return array{rows: array<int, array{id: string, cycle_number: int, device_name: string, status: string, release_decision: string|null}>, error: bool}
     */
    private function buildTodayCycles(Tenant $tenant): array
    {
        try {
            $todayStart = now($tenant->timezone)->startOfDay()->setTimezone('UTC');
            $todayEnd = now($tenant->timezone)->endOfDay()->setTimezone('UTC');

            $rows = Cycle::whereBetween('started_at', [$todayStart, $todayEnd])
                ->with(['device:id,name', 'release:id,cycle_id,decision'])
                ->orderBy('cycle_number')
                ->get()
                ->map(fn (Cycle $cycle) => [
                    'id' => $cycle->id,
                    'cycle_number' => $cycle->cycle_number,
                    'device_name' => $cycle->device->name,
                    'status' => $cycle->status->value,
                    'release_decision' => $cycle->release?->decision?->value,
                ])
                ->all();

            return ['rows' => $rows, 'error' => false];
        } catch (Throwable $e) {
            report($e);

            return ['rows' => [], 'error' => true];
        }
    }

    /**
     * @param  array{site: bool, autoclave: bool, program: bool, cycle: bool}  $onboarding
     * @return OnboardingStepData[]
     */
    private function onboardingSteps(array $onboarding, User $user): array
    {
        return [
            new OnboardingStepData('add_site', 'Add a site', $onboarding['site'], $user->can('sites.manage')),
            new OnboardingStepData('add_autoclave', 'Add an autoclave', $onboarding['autoclave'], $user->can('devices.manage')),
            new OnboardingStepData('add_program', 'Add a program', $onboarding['program'], $user->can('devices.manage')),
            new OnboardingStepData('run_first_cycle', 'Run your first cycle', $onboarding['cycle'], $user->can('cycles.manage')),
        ];
    }

    /**
     * @return array{0: RecentProcedureData[], 1: bool}
     */
    private function myRecentProcedures(User $user): array
    {
        if (! $user->can('usages.view')) {
            return [[], false];
        }

        try {
            $procedures = LabelUsage::where('practitioner_id', $user->id)
                ->orderByDesc('used_at')
                ->limit(5)
                ->get()
                ->map(fn (LabelUsage $usage) => new RecentProcedureData($usage->label_id, $usage->procedure, $usage->used_at->toIso8601String()))
                ->all();

            return [$procedures, false];
        } catch (Throwable $e) {
            report($e);

            return [[], true];
        }
    }
}
