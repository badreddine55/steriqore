<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * State machine (CLAUDE.md): draft -> ordered -> partially_received ->
 * received -> closed | cancelled. Enforced in code
 * (PurchaseOrderStatus/PurchaseOrderActions), not a DB CHECK constraint —
 * transitions depend on line quantities, not just the current status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('draft');
            $table->timestampTz('ordered_at')->nullable();
            $table->timestampTz('expected_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('ALTER TABLE purchase_orders ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE purchase_orders FORCE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON purchase_orders
                USING (tenant_id = current_setting('app.tenant_id', true)::uuid)
                WITH CHECK (tenant_id = current_setting('app.tenant_id', true)::uuid)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
