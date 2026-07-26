<?php

namespace App\Http\Controllers\Api\V1\Sterilization;

use App\Domain\Sterilization\Actions\AttachCycleFileAction;
use App\Domain\Sterilization\Actions\RemoveCycleAttachmentAction;
use App\Domain\Sterilization\Data\CycleAttachmentData;
use App\Domain\Sterilization\Models\Cycle;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Sterilization\AttachCycleFileRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class CycleAttachmentController extends Controller
{
    /**
     * @return array<int, CycleAttachmentData>
     */
    public function index(Cycle $cycle): array
    {
        $this->authorize('view', $cycle);

        return $cycle->getMedia('attachments')
            ->map(fn (Media $media) => CycleAttachmentData::fromModel($media))
            ->all();
    }

    public function store(Cycle $cycle, AttachCycleFileRequest $request, AttachCycleFileAction $action): CycleAttachmentData
    {
        $this->authorize('update', $cycle);

        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422);

        $media = $action->execute($cycle, $request->user(), $file);

        return CycleAttachmentData::fromModel($media);
    }

    public function destroy(Cycle $cycle, Media $media, Request $request, RemoveCycleAttachmentAction $action): Response
    {
        $this->authorize('update', $cycle);
        abort_if($media->model_type !== Cycle::class || $media->model_id !== $cycle->id, 404);

        $action->execute($cycle, $media, $request->user());

        return response()->noContent();
    }
}
