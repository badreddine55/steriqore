<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One row per pouch (cycle_items.id is unique here — CLAUDE.md: "One label
 * per pouch"). packaging_type/storage_condition/shelf_life_days/
 * sterilized_at/use_by_date are all frozen at creation time from whichever
 * dlu_rules row matched — never recomputed later, so a future change to
 * the rule table never rewrites past evidence (CLAUDE.md's LABELS
 * section). Invariant #2 ("no label for a cycle that isn't
 * released+compliant") is enforced in the action, not the schema — it
 * depends on the parent cycle's status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('labels', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('cycle_item_id')->unique()->constrained()->restrictOnDelete();
            $table->string('status')->default('created');
            $table->string('packaging_type');
            $table->string('storage_condition');
            $table->unsignedInteger('shelf_life_days');
            $table->timestampTz('sterilized_at');
            $table->date('use_by_date');
            $table->unsignedInteger('print_counter')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        DB::statement('ALTER TABLE labels ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE labels FORCE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON labels
                USING (tenant_id = current_setting('app.tenant_id', true)::uuid)
                WITH CHECK (tenant_id = current_setting('app.tenant_id', true)::uuid)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('labels');
    }
};
