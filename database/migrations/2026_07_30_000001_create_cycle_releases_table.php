<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A cycle_release row is immutable (CLAUDE.md invariant #3) — one per
 * cycle (unique cycle_id: a cycle is released or rejected exactly once;
 * there is no re-release). Mistakes past this point are corrected by a
 * non_conformity + recall (S4), never by UPDATE — same append-only trigger
 * already used for stock_movements/audit_events.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cycle_releases', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('cycle_id')->unique()->constrained()->restrictOnDelete();
            $table->string('decision');
            $table->foreignUuid('released_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('released_at');
            $table->text('reason')->nullable();
            $table->timestampTz('created_at');

            $table->index(['tenant_id', 'decision']);
        });

        DB::statement(<<<'SQL'
            CREATE TRIGGER cycle_releases_no_update_delete
                BEFORE UPDATE OR DELETE ON cycle_releases
                FOR EACH ROW EXECUTE FUNCTION prevent_append_only_table_modification()
            SQL);

        DB::statement('ALTER TABLE cycle_releases ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE cycle_releases FORCE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON cycle_releases
                FOR SELECT
                USING (tenant_id = current_setting('app.tenant_id', true)::uuid)
            SQL);
        DB::statement(<<<'SQL'
            CREATE POLICY allow_insert ON cycle_releases
                FOR INSERT
                WITH CHECK (true)
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS cycle_releases_no_update_delete ON cycle_releases');
        Schema::dropIfExists('cycle_releases');
    }
};
