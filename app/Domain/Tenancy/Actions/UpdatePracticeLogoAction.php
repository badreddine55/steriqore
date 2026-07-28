<?php

namespace App\Domain\Tenancy\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Mirrors AttachCycleFileAction's use of spatie/laravel-medialibrary. The
 * 'logo' collection is registered singleFile() on Tenant, so this replaces
 * any previous logo rather than accumulating files.
 */
class UpdatePracticeLogoAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    public function execute(Tenant $tenant, User $actor, UploadedFile $file, string $reason): Media
    {
        $media = $tenant->addMedia($file)->toMediaCollection('logo');

        $this->recordAuditEvent->execute(
            tenant: $tenant,
            actor: $actor,
            action: 'tenant.logo_updated',
            subjectType: Tenant::class,
            subjectId: $tenant->id,
            newValues: ['media_id' => $media->id, 'file_name' => $media->file_name],
            reason: $reason,
            request: $this->request,
        );

        return $media;
    }
}
