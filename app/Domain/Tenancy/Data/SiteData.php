<?php

namespace App\Domain\Tenancy\Data;

use App\Domain\Tenancy\Models\Site;
use Spatie\LaravelData\Data;

class SiteData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $timezone,
        public readonly bool $is_primary,
    ) {}

    public static function fromModel(Site $site): self
    {
        return new self($site->id, $site->name, $site->timezone, $site->is_primary);
    }
}
