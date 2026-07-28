<?php

namespace App\Domain\Tenancy\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Tenancy\Models\Site;
use App\Models\User;
use App\Support\Api\ApiException;
use Illuminate\Http\Request;

class UpdateSiteAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(Site $site, User $actor, array $attributes): Site
    {
        if (
            isset($attributes['name'])
            && Site::where('name', $attributes['name'])->where('id', '!=', $site->id)->exists()
        ) {
            throw new ApiException('SITE_NAME_TAKEN', 'A site with this name already exists.', 409);
        }

        $oldValues = $site->only(array_keys($attributes));
        $site->update($attributes);

        $this->recordAuditEvent->execute(
            tenant: $site->tenant,
            actor: $actor,
            action: 'site.updated',
            subjectType: Site::class,
            subjectId: $site->id,
            oldValues: $oldValues,
            newValues: $attributes,
            request: $this->request,
        );

        return $site;
    }
}
