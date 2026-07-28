<?php

namespace App\Http\Controllers\Web\Tenancy;

use App\Domain\Identity\Enums\TenantRole;
use App\Domain\Inventory\Actions\UpdateAlertSettingsAction;
use App\Domain\Inventory\Actions\UpdateDigestSubscriptionsAction;
use App\Domain\Inventory\Data\AlertSettingsData;
use App\Domain\Inventory\Data\DigestSubscriptionData;
use App\Domain\Inventory\Models\AlertSettings;
use App\Domain\Inventory\Models\DigestSubscription;
use App\Domain\Labeling\Actions\CreateDluRuleAction;
use App\Domain\Labeling\Actions\RenderLabelFormatSampleAction;
use App\Domain\Labeling\Actions\UpdateDluRuleAction;
use App\Domain\Labeling\Actions\UpdateLabelFormatAction;
use App\Domain\Labeling\Data\DluRuleData;
use App\Domain\Labeling\Data\LabelFormatVersionData;
use App\Domain\Labeling\Enums\LabelSheetLayout;
use App\Domain\Labeling\Enums\PrimaryLabelCode;
use App\Domain\Labeling\Models\DluRule;
use App\Domain\Tenancy\Actions\UpdatePracticeIdentityAction;
use App\Domain\Tenancy\Actions\UpdatePracticeLogoAction;
use App\Domain\Tenancy\Data\PracticeIdentityData;
use App\Domain\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Inventory\UpdateAlertSettingsRequest;
use App\Http\Requests\Api\V1\Inventory\UpdateDigestSubscriptionsRequest;
use App\Http\Requests\Api\V1\Labeling\CreateDluRuleRequest;
use App\Http\Requests\Api\V1\Labeling\SampleLabelFormatRequest;
use App\Http\Requests\Api\V1\Labeling\UpdateDluRuleRequest;
use App\Http\Requests\Api\V1\Labeling\UpdateLabelFormatRequest;
use App\Http\Requests\Api\V1\Tenancy\UpdatePracticeIdentityRequest;
use App\Http\Requests\Api\V1\Tenancy\UpdatePracticeLogoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelPdf\PdfBuilder;

/**
 * One page, four sections (UI-28) — not four routes. Owner/admin
 * (practice_settings.manage) can reach every section; only owner
 * (evidence_settings.manage) can actually save label format or DLU rule
 * changes — deliberately a NEW, separate permission from labels.manage
 * (which the mobile API's DluRuleController still uses unchanged for
 * stock_manager/owner/admin) rather than tightening that existing,
 * independently-established capability. Hiding a control is UX; every
 * write below still checks the real permission itself.
 */
class PracticeSettingsController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('practice_settings.manage'), 403);

        $tenant = Tenant::current();
        $dluRules = DluRule::orderBy('packaging_type')->orderBy('storage_condition')->get();
        $currentFormat = UpdateLabelFormatAction::current($tenant);

        $subscriptionsByRole = DigestSubscription::where('tenant_id', $tenant->id)->get()->keyBy('role');
        $digestSubscriptions = collect(TenantRole::cases())->map(
            fn (TenantRole $role) => $subscriptionsByRole->get($role->value) ?? new DigestSubscription([
                'role' => $role->value,
                'frequency' => 'daily',
                'enabled' => false,
            ])
        );

        return Inertia::render('tenancy/practice-settings/index', [
            'identity' => PracticeIdentityData::fromModel($tenant),
            'dluRules' => DluRuleData::collect($dluRules),
            'labelFormat' => $currentFormat ? LabelFormatVersionData::fromModel($currentFormat) : null,
            'alertSettings' => AlertSettingsData::fromModel(AlertSettings::forTenant($tenant)),
            'digestSubscriptions' => DigestSubscriptionData::collect($digestSubscriptions),
            'canManageEvidence' => $request->user()->can('evidence_settings.manage'),
        ]);
    }

    public function updateIdentity(UpdatePracticeIdentityRequest $request, UpdatePracticeIdentityAction $action): RedirectResponse
    {
        abort_unless($request->user()->can('practice_settings.manage'), 403);

        $action->execute(
            Tenant::current(),
            $request->user(),
            $request->safe()->except('reason'),
            $request->validated('reason'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Practice identity updated.']);

        return back();
    }

    public function updateLogo(UpdatePracticeLogoRequest $request, UpdatePracticeLogoAction $action): RedirectResponse
    {
        abort_unless($request->user()->can('practice_settings.manage'), 403);

        $action->execute(Tenant::current(), $request->user(), $request->file('logo'), $request->validated('reason'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Logo updated.']);

        return back();
    }

    public function storeDluRule(CreateDluRuleRequest $request, CreateDluRuleAction $action): RedirectResponse
    {
        abort_unless($request->user()->can('evidence_settings.manage'), 403);

        $rule = $action->execute(
            Tenant::current(),
            $request->user(),
            $request->safe()->except('reason'),
            $request->validated('reason'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "Rule for \"{$rule->packaging_type} / {$rule->storage_condition}\" added."]);

        return back();
    }

    public function updateDluRule(DluRule $dluRule, UpdateDluRuleRequest $request, UpdateDluRuleAction $action): RedirectResponse
    {
        abort_unless($request->user()->can('evidence_settings.manage'), 403);

        $action->execute(
            $dluRule,
            $request->user(),
            $request->safe()->except('reason'),
            $request->validated('reason'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'DLU rule updated. New use-by dates apply to labels generated from now on.']);

        return back();
    }

    public function updateLabelFormat(UpdateLabelFormatRequest $request, UpdateLabelFormatAction $action): RedirectResponse
    {
        abort_unless($request->user()->can('evidence_settings.manage'), 403);

        $action->execute(
            Tenant::current(),
            $request->user(),
            $request->safe()->except('reason'),
            $request->validated('reason'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Label format updated. Applies to labels printed from now on.']);

        return back();
    }

    public function sampleLabelFormat(SampleLabelFormatRequest $request, RenderLabelFormatSampleAction $action): PdfBuilder
    {
        abort_unless($request->user()->can('evidence_settings.manage'), 403);

        return $action->execute(
            $request->user(),
            PrimaryLabelCode::from($request->validated('primary_code')),
            LabelSheetLayout::from($request->validated('sheet_layout')),
            $request->boolean('show_batch_number'),
            $request->boolean('show_product_name'),
        );
    }

    public function updateAlertSettings(UpdateAlertSettingsRequest $request, UpdateAlertSettingsAction $action): RedirectResponse
    {
        abort_unless($request->user()->can('practice_settings.manage'), 403);

        $action->execute(
            Tenant::current(),
            $request->user(),
            $request->safe()->except('reason'),
            $request->validated('reason'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Alert settings updated.']);

        return back();
    }

    public function previewNearExpiryImpact(Request $request, UpdateAlertSettingsAction $action): JsonResponse
    {
        abort_unless($request->user()->can('practice_settings.manage'), 403);

        $validated = $request->validate(['near_expiry_days' => ['required', 'integer', 'min:1', 'max:365']]);

        return response()->json($action->previewNearExpiryImpact(Tenant::current(), $validated['near_expiry_days']));
    }

    public function updateDigestSubscriptions(UpdateDigestSubscriptionsRequest $request, UpdateDigestSubscriptionsAction $action): RedirectResponse
    {
        abort_unless($request->user()->can('practice_settings.manage'), 403);

        $action->execute(
            Tenant::current(),
            $request->user(),
            $request->validated('subscriptions'),
            $request->validated('reason'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Digest subscriptions updated.']);

        return back();
    }
}
