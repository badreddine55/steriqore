<?php

namespace App\Domain\Purchasing\Data;

use App\Domain\Purchasing\Models\Supplier;
use Spatie\LaravelData\Data;

class SupplierData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly ?string $address,
    ) {}

    public static function fromModel(Supplier $supplier): self
    {
        return new self($supplier->id, $supplier->name, $supplier->email, $supplier->phone, $supplier->address);
    }
}
