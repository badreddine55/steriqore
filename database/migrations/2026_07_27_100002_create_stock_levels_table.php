<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A projection, not a source of truth: every row here is
 * SUM(stock_movements.qty) grouped by (batch_id, location_id), maintained
 * incrementally by RecordStockMovementAction and fully rebuildable by
 * RebuildStockLevelsAction. No route creates or updates a row here
 * directly — CLAUDE.md invariant #1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_levels', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('location_id')->constrained('storage_locations')->cascadeOnDelete();
            $table->integer('quantity')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'batch_id', 'location_id']);
        });

        DB::statement('ALTER TABLE stock_levels ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE stock_levels FORCE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON stock_levels
                USING (tenant_id = current_setting('app.tenant_id', true)::uuid)
                WITH CHECK (tenant_id = current_setting('app.tenant_id', true)::uuid)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_levels');
    }
};
