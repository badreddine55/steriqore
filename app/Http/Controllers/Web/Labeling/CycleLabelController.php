<?php

namespace App\Http\Controllers\Web\Labeling;

use App\Domain\Labeling\Actions\GenerateCycleLabelsAction;
use App\Domain\Labeling\Data\DluRuleData;
use App\Domain\Labeling\Data\LabelData;
use App\Domain\Labeling\Models\DluRule;
use App\Domain\Labeling\Models\Label;
use App\Domain\Sterilization\Data\CycleData;
use App\Domain\Sterilization\Data\CycleItemData;
use App\Domain\Sterilization\Enums\CycleStatus;
use App\Domain\Sterilization\Models\Cycle;
use App\Domain\Sterilization\Models\CycleItem;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Labeling\GenerateCycleLabelsRequest;
use App\Support\Data\OptionData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The "label sheet" for a cycle: one row per load item, each with its label
 * once generated (CLAUDE.md invariant #2 — only for a released+compliant
 * cycle, enforced by GenerateCycleLabelsAction itself, not re-checked here).
 */
class CycleLabelController extends Controller
{
    public function index(Cycle $cycle, Request $request): Response
    {
        $this->authorize('view', $cycle);

        $cycle->loadMissing(['device']);
        $items = $cycle->items()->orderBy('sequence_in_cycle')->get();
        $labels = Label::whereIn('cycle_item_id', $items->pluck('id'))->get();

        $dluRules = DluRule::orderBy('packaging_type')->orderBy('storage_condition')->get();

        return Inertia::render('sterilization/cycles/labels', [
            'cycle' => CycleData::fromModel($cycle),
            'device' => new OptionData($cycle->device->id, $cycle->device->name),
            'items' => CycleItemData::collect(
                $items->map(fn (CycleItem $item) => CycleItemData::fromModel($item))
            ),
            'labels' => LabelData::collect(
                $labels->map(fn (Label $label) => LabelData::fromModel($label))
            ),
            'dluRules' => DluRuleData::collect($dluRules),
            'canGenerate' => $cycle->status === CycleStatus::Released
                && $request->user()->can('create', Label::class),
        ]);
    }

    public function store(Cycle $cycle, GenerateCycleLabelsRequest $request, GenerateCycleLabelsAction $action): RedirectResponse
    {
        $this->authorize('create', Label::class);

        $action->execute(
            $cycle,
            $request->user(),
            $request->string('packaging_type')->toString(),
            $request->string('storage_condition')->toString(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Labels generated.']);

        return redirect()->route('cycles.labels.index', $cycle);
    }
}
