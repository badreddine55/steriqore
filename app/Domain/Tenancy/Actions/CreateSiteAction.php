<?php

namespace App\Domain\Tenancy\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Tenancy\Models\Site;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Api\ApiException;
use Illuminate\Http\Request;

class CreateSiteAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(Tenant $tenant, User $actor, array $attributes): Site
    {
        if (Site::where('name', $attributes['name'])->exists()) {
            throw new ApiException('SITE_NAME_TAKEN', 'A site with this name already exists.', 409);
        }

        $site = Site::create($attributes + ['tenant_id' => $tenant->id]);

        $this->recordAuditEvent->execute(
            tenant: $tenant,
            actor: $actor,
            action: 'site.created',
            subjectType: Site::class,
            subjectId: $site->id,
            newValues: $attributes,
            request: $this->request,
        );

        return $site;
    }
}
