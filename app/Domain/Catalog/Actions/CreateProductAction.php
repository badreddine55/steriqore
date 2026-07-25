<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\Product;
use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Api\ApiException;
use Illuminate\Http\Request;

class CreateProductAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(Tenant $tenant, User $actor, array $attributes): Product
    {
        if (Product::where('reference', $attributes['reference'])->exists()) {
            throw new ApiException('PRODUCT_REFERENCE_TAKEN', 'A product with this reference already exists.', 409);
        }

        $product = Product::create($attributes + ['tenant_id' => $tenant->id]);
        // Columns not in $attributes (unit, min_threshold, is_sterilizable)
        // fall back to their DB-level defaults, which the in-memory model
        // never sees unless re-read.
        $product->refresh();

        $this->recordAuditEvent->execute(
            tenant: $tenant,
            actor: $actor,
            action: 'product.created',
            subjectType: Product::class,
            subjectId: $product->id,
            newValues: $attributes,
            request: $this->request,
        );

        return $product;
    }
}
