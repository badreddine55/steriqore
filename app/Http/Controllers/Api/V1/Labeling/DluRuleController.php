<?php

namespace App\Http\Controllers\Api\V1\Labeling;

use App\Domain\Labeling\Actions\CreateDluRuleAction;
use App\Domain\Labeling\Actions\DeleteDluRuleAction;
use App\Domain\Labeling\Actions\UpdateDluRuleAction;
use App\Domain\Labeling\Data\DluRuleData;
use App\Domain\Labeling\Models\DluRule;
use App\Domain\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Labeling\CreateDluRuleRequest;
use App\Http\Requests\Api\V1\Labeling\UpdateDluRuleRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DluRuleController extends Controller
{
    /**
     * @return array<int, DluRuleData>
     */
    public function index(): array
    {
        $this->authorize('viewAny', DluRule::class);

        return DluRule::orderBy('packaging_type')->orderBy('storage_condition')->get()
            ->map(fn (DluRule $rule) => DluRuleData::fromModel($rule))
            ->all();
    }

    public function store(CreateDluRuleRequest $request, CreateDluRuleAction $action): DluRuleData
    {
        $this->authorize('create', DluRule::class);

        $rule = $action->execute(
            Tenant::current(),
            $request->user(),
            $request->safe()->except('reason'),
            $request->validated('reason'),
        );

        return DluRuleData::fromModel($rule);
    }

    public function update(DluRule $rule, UpdateDluRuleRequest $request, UpdateDluRuleAction $action): DluRuleData
    {
        $this->authorize('update', $rule);

        $rule = $action->execute(
            $rule,
            $request->user(),
            $request->safe()->except('reason'),
            $request->validated('reason'),
        );

        return DluRuleData::fromModel($rule);
    }

    public function destroy(DluRule $rule, Request $request, DeleteDluRuleAction $action): Response
    {
        $this->authorize('delete', $rule);

        $action->execute($rule, $request->user());

        return response()->noContent();
    }
}
