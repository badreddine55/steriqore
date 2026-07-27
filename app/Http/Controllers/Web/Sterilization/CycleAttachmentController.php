<?php

namespace App\Http\Controllers\Web\Sterilization;

use App\Domain\Sterilization\Actions\AttachCycleFileAction;
use App\Domain\Sterilization\Actions\RemoveCycleAttachmentAction;
use App\Domain\Sterilization\Models\Cycle;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Sterilization\AttachCycleFileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class CycleAttachmentController extends Controller
{
    public function store(Cycle $cycle, AttachCycleFileRequest $request, AttachCycleFileAction $action): RedirectResponse
    {
        $this->authorize('update', $cycle);

        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422);

        $action->execute($cycle, $request->user(), $file);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'File attached.']);

        return back();
    }

    public function destroy(Cycle $cycle, Media $media, Request $request, RemoveCycleAttachmentAction $action): RedirectResponse
    {
        $this->authorize('update', $cycle);
        abort_if($media->model_type !== Cycle::class || $media->model_id !== $cycle->id, 404);

        $action->execute($cycle, $media, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Attachment removed.']);

        return back();
    }
}
