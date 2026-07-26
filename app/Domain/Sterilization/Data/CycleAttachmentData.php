<?php

namespace App\Domain\Sterilization\Data;

use Spatie\LaravelData\Data;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class CycleAttachmentData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $file_name,
        public readonly ?string $mime_type,
        public readonly int $size,
        public readonly string $url,
        public readonly string $created_at,
    ) {}

    public static function fromModel(Media $media): self
    {
        return new self(
            // The base Spatie\MediaLibrary\...\Media class's own docblock
            // still says int (its default bigint PK); our config points
            // media_model at a UUID subclass instead (see App\Support\Media
            // \Media), so this really is a string at runtime.
            (string) $media->id,
            $media->file_name,
            $media->mime_type,
            $media->size,
            $media->getTemporaryUrl(now()->addMinutes(10)),
            $media->created_at->toIso8601String(),
        );
    }
}
