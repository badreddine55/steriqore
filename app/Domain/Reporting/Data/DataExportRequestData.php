<?php

namespace App\Domain\Reporting\Data;

use App\Domain\Reporting\Models\DataExportRequest;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelData\Data;

class DataExportRequestData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $status,
        public readonly ?string $download_url,
        public readonly ?string $error,
        public readonly ?string $completed_at,
    ) {}

    public static function fromModel(DataExportRequest $request): self
    {
        return new self(
            $request->id,
            $request->status->value,
            $request->file_path ? Storage::disk('backups')->temporaryUrl($request->file_path, now()->addMinutes(30)) : null,
            $request->error,
            $request->completed_at?->toIso8601String(),
        );
    }
}
