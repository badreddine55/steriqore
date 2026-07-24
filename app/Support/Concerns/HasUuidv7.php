<?php

namespace App\Support\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Str;

/**
 * Primary keys are UUIDv7 (time-ordered) rather than Laravel's default
 * "ordered UUIDv4", so ids sort chronologically and match the DB-level
 * `uuidv7()` default set on every tenant-owned table (see docs/erd.md).
 */
trait HasUuidv7
{
    use HasUuids;

    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }
}
