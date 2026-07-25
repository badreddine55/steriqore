<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\Product;
use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Models\User;
use App\Support\Api\ApiException;
use Illuminate\Http\Request;

class UpdateProductAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(Product $product, User $actor, array $attributes): Product
    {
        if (
            isset($attributes['reference'])
            && Product::where('reference', $attributes['reference'])->where('id', '!=', $product->id)->exists()
        ) {
            throw new ApiException('PRODUCT_REFERENCE_TAKEN', 'A product with this reference already exists.', 409);
        }

        $oldValues = $product->only(array_keys($attributes));
        $product->update($attributes);

        $this->recordAuditEvent->execute(
            tenant: $product->tenant,
            actor: $actor,
            action: 'product.updated',
            subjectType: Product::class,
            subjectId: $product->id,
            oldValues: $oldValues,
            newValues: $attributes,
            request: $this->request,
        );

        return $product;
    }
}
