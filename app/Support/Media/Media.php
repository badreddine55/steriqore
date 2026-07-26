<?php

namespace App\Support\Media;

use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasUuidv7;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;

/**
 * Every model config/media-library.php's `media_model` points here instead
 * of Spatie's default — the stock model's `id`/`model_id` are bigint, which
 * doesn't fit a codebase that's UUIDv7 everywhere (migration uses
 * uuidMorphs, not morphs). BelongsToTenant also gives media its own RLS
 * tenant isolation, not just an implicit scope inherited through whatever
 * it's attached to.
 */
class Media extends SpatieMedia
{
    use BelongsToTenant;
    use HasUuidv7;
}
