<?php

namespace App\Domain\Sterilization\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Sterilization\Models\Cycle;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class AttachCycleFileAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    public function execute(Cycle $cycle, User $actor, UploadedFile $file): Media
    {
        $media = $cycle->addMedia($file)->toMediaCollection('attachments');

        $this->recordAuditEvent->execute(
            tenant: $cycle->tenant,
            actor: $actor,
            action: 'cycle_attachment.added',
            subjectType: Cycle::class,
            subjectId: $cycle->id,
            newValues: ['media_id' => $media->id, 'file_name' => $media->file_name],
            request: $this->request,
        );

        return $media;
    }
}
