<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every print of a label writes one row here (the first print included) —
 * a reprint (sequence > 1) additionally requires a reason at the action
 * level (CLAUDE.md invariant #4). Append-only, like stock_movements/
 * cycle_releases.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('label_prints', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('label_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('printed_by_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('sequence');
            $table->text('reason')->nullable();
            $table->timestampTz('printed_at');
            $table->timestampTz('created_at');

            $table->unique(['label_id', 'sequence']);
        });

        DB::statement(<<<'SQL'
            CREATE TRIGGER label_prints_no_update_delete
                BEFORE UPDATE OR DELETE ON label_prints
                FOR EACH ROW EXECUTE FUNCTION prevent_append_only_table_modification()
            SQL);

        DB::statement('ALTER TABLE label_prints ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE label_prints FORCE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON label_prints
                FOR SELECT
                USING (tenant_id = current_setting('app.tenant_id', true)::uuid)
            SQL);
        DB::statement(<<<'SQL'
            CREATE POLICY allow_insert ON label_prints
                FOR INSERT
                WITH CHECK (true)
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS label_prints_no_update_delete ON label_prints');
        Schema::dropIfExists('label_prints');
    }
};
