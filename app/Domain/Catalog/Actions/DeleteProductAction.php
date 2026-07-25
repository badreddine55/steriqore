<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\Product;
use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Soft delete only — deletion is soft everywhere (CLAUDE.md invariant #8).
 * Products aren't an evidence table (unlike cycles/labels/movements), so
 * this route exists at all, unlike those.
 */
class DeleteProductAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    public function execute(Product $product, User $actor): void
    {
        $product->delete();

        $this->recordAuditEvent->execute(
            tenant: $product->tenant,
            actor: $actor,
            action: 'product.deleted',
            subjectType: Product::class,
            subjectId: $product->id,
            request: $this->request,
        );
    }
}
