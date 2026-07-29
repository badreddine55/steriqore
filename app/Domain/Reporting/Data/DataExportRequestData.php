<?php

namespace App\Domain\Reporting\Data;

use App\Domain\Reporting\Models\DataExportRequest;
use Spatie\LaravelData\Data;

/**
 * UI-29 removed download_url: a presigned URL is never stored or rendered
 * from a polled/read response, only minted on demand by
 * GenerateDataExportDownloadUrlAction and audited at that moment. The
 * manifest fields let the ready-state screen show what's inside before the
 * user leaves the practice.
 */
class DataExportRequestData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $status,
        public readonly string $requested_by_name,
        public readonly string $requested_at,
        public readonly ?string $error,
        public readonly ?string $completed_at,
        public readonly ?int $size_bytes,
        public readonly ?int $record_count,
        public readonly ?int $file_count,
        /** @var array<string, int>|null */
        public readonly ?array $table_manifest,
        public readonly ?string $expires_at,
    ) {}

    public static function fromModel(DataExportRequest $request): self
    {
        return new self(
            $request->id,
            $request->status->value,
            $request->requestedBy->name,
            $request->created_at->toIso8601String(),
            $request->error,
            $request->completed_at?->toIso8601String(),
            $request->size_bytes,
            $request->record_count,
            $request->file_count,
            $request->table_manifest,
            $request->expires_at?->toIso8601String(),
        );
    }
}
