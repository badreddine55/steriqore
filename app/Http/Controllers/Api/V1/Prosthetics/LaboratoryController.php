<?php

namespace App\Http\Controllers\Api\V1\Prosthetics;

use App\Domain\Prosthetics\Data\LaboratoryData;
use App\Domain\Prosthetics\Models\Laboratory;
use App\Domain\Prosthetics\Models\ProstheticCase;
use App\Domain\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LaboratoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Laboratory::class);

        $tenant = Tenant::current() ?? $request->user()?->tenants()->first();
        $tenantId = $tenant->id;

        $labs = Laboratory::where('tenant_id', $tenantId)->get();

        if ($labs->isEmpty()) {
            // Seed initial default laboratories if empty
            $defaultLabs = [
                [
                    'tenant_id' => $tenantId,
                    'name' => 'Laboratoire Dentaire Moderne',
                    'phone' => '01 42 68 55 00',
                    'email' => 'contact@labo-moderne.fr',
                    'address' => '14 Rue de la Paix, 75002 Paris',
                    'contact_person' => 'Jean-Marc V.',
                    'specialties' => ['Céramique', 'Zircone', 'Implants', 'Châssis'],
                ],
                [
                    'tenant_id' => $tenantId,
                    'name' => 'ProsthLab Excellence',
                    'phone' => '01 55 43 21 00',
                    'email' => 'commandes@prosthlab.fr',
                    'address' => '28 Avenue Foch, 75116 Paris',
                    'contact_person' => 'Claire Renoir',
                    'specialties' => ['Aligners', 'Facettes', 'Inlays/Onlays', 'E-Max'],
                ],
                [
                    'tenant_id' => $tenantId,
                    'name' => 'BioDent Precision',
                    'phone' => '01 48 99 12 34',
                    'email' => 'support@biodent.fr',
                    'address' => '5 Boulevard Haussmann, 75009 Paris',
                    'contact_person' => 'Marc Antoine',
                    'specialties' => ['Adjointes', 'Prothèses Complètes', 'Gouttières'],
                ],
            ];
            foreach ($defaultLabs as $data) {
                Laboratory::create($data);
            }
            $labs = Laboratory::where('tenant_id', $tenantId)->get();
        }

        $result = $labs->map(function ($lab) use ($tenantId) {
            $activeCount = ProstheticCase::where('tenant_id', $tenantId)
                ->where('laboratory_id', $lab->id)
                ->whereNotIn('status', ['placed', 'cancelled', 'remade'])
                ->count();
            return LaboratoryData::fromModel($lab, $activeCount);
        });

        return response()->json($result);
    }
}
