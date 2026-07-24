<?php

namespace App\Domain\Tenancy\Data;

use App\Domain\Tenancy\Models\Tenant;
use Spatie\LaravelData\Data;

class TenantData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $slug,
    ) {}

    public static function fromModel(Tenant $tenant): self
    {
        return new self($tenant->id, $tenant->name, $tenant->slug);
    }
}
