<?php

namespace App\Domain\Sterilization\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Sterilization\Models\Cycle;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class RemoveCycleAttachmentAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    public function execute(Cycle $cycle, Media $media, User $actor): void
    {
        $mediaId = $media->id;
        $fileName = $media->file_name;
        $media->delete();

        $this->recordAuditEvent->execute(
            tenant: $cycle->tenant,
            actor: $actor,
            action: 'cycle_attachment.removed',
            subjectType: Cycle::class,
            subjectId: $cycle->id,
            oldValues: ['media_id' => $mediaId, 'file_name' => $fileName],
            request: $this->request,
        );
    }
}
