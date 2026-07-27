<?php

namespace App\Http\Controllers\Web\Purchasing;

use App\Domain\Catalog\Models\Product;
use App\Domain\Purchasing\Actions\AttachSupplierProductAction;
use App\Domain\Purchasing\Models\Supplier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Purchasing\AttachSupplierProductRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Attach/update only — the domain has no detach route either
 * (Api\V1\Purchasing\SupplierProductController has no destroy()).
 */
class SupplierProductController extends Controller
{
    public function store(Supplier $supplier, AttachSupplierProductRequest $request, AttachSupplierProductAction $action): RedirectResponse
    {
        $this->authorize('update', $supplier);

        $product = Product::findOrFail($request->string('product_id')->toString());

        $action->execute($supplier, $product, $request->safe()->except('product_id'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Product linked to supplier.']);

        return back();
    }
}
