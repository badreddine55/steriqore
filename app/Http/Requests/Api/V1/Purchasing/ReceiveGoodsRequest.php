<?php

namespace App\Http\Requests\Api\V1\Purchasing;

use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReceiveGoodsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $tenantId = Tenant::current()?->id;
        $purchaseOrder = $this->route('purchaseOrder');
        $purchaseOrderId = $purchaseOrder instanceof PurchaseOrder ? $purchaseOrder->id : null;

        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.purchase_order_line_id' => [
                'required', 'uuid',
                Rule::exists('purchase_order_lines', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('purchase_order_id', $purchaseOrderId),
            ],
            'lines.*.batch_number' => ['required', 'string', 'max:255'],
            'lines.*.expiry_date' => ['nullable', 'date'],
            'lines.*.qty' => ['required', 'integer', 'min:1'],
            'lines.*.discrepancy_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
