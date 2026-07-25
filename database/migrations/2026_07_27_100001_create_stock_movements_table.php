<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The append-only ledger stock is derived from (CLAUDE.md invariant #1):
 * no endpoint sets a quantity directly, stock_levels is a projection built
 * by summing this table, rebuildable by replay
 * (App\Domain\Inventory\Actions\RebuildStockLevelsAction).
 *
 * `qty` is signed: positive credits a location, negative debits it. A
 * transfer is two linked rows (one negative at the source, one positive at
 * the destination) sharing `group_id` — there's no single row that "moves"
 * stock between two locations, only debits and credits, so stock_levels
 * stays a pure SUM(qty) per (batch_id, location_id) regardless of movement
 * type.
 *
 * Append-only twice over, like audit_events: no update/delete route, and a
 * DB trigger that refuses UPDATE/DELETE regardless of role.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->foreignUuid('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('location_id')->constrained('storage_locations')->cascadeOnDelete();
            $table->integer('qty');
            $table->uuid('group_id')->nullable();
            $table->text('reason')->nullable();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('idempotency_key')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at');

            $table->index(['tenant_id', 'batch_id', 'location_id']);
            $table->index(['tenant_id', 'group_id']);
            $table->unique(['tenant_id', 'idempotency_key']);
        });

        // Generic (unlike prevent_audit_events_modification, whose message
        // hardcodes that table's name) — reused by every other append-only
        // evidence table from here on.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_append_only_table_modification() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION '% is append-only: % is not permitted', TG_TABLE_NAME, TG_OP;
            END;
            $$ LANGUAGE plpgsql
            SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER stock_movements_no_update_delete
                BEFORE UPDATE OR DELETE ON stock_movements
                FOR EACH ROW EXECUTE FUNCTION prevent_append_only_table_modification()
            SQL);

        DB::statement('ALTER TABLE stock_movements ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE stock_movements FORCE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON stock_movements
                FOR SELECT
                USING (tenant_id = current_setting('app.tenant_id', true)::uuid)
            SQL);
        DB::statement(<<<'SQL'
            CREATE POLICY allow_insert ON stock_movements
                FOR INSERT
                WITH CHECK (true)
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS stock_movements_no_update_delete ON stock_movements');
        Schema::dropIfExists('stock_movements');
    }
};
