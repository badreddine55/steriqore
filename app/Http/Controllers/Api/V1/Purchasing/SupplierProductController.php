<?php

namespace App\Http\Controllers\Api\V1\Purchasing;

use App\Domain\Catalog\Models\Product;
use App\Domain\Purchasing\Actions\AttachSupplierProductAction;
use App\Domain\Purchasing\Data\SupplierProductData;
use App\Domain\Purchasing\Models\Supplier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Purchasing\AttachSupplierProductRequest;

class SupplierProductController extends Controller
{
    /**
     * @return array<int, SupplierProductData>
     */
    public function index(Supplier $supplier): array
    {
        $this->authorize('view', $supplier);

        return $supplier->supplierProducts()->get()
            ->map(fn ($sp) => SupplierProductData::fromModel($sp))
            ->all();
    }

    public function store(Supplier $supplier, AttachSupplierProductRequest $request, AttachSupplierProductAction $action): SupplierProductData
    {
        $this->authorize('update', $supplier);

        $product = Product::findOrFail($request->string('product_id')->toString());

        $link = $action->execute($supplier, $product, $request->safe()->except('product_id'));

        return SupplierProductData::fromModel($link);
    }
}
