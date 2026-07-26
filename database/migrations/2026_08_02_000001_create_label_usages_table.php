<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The last link in "pouch -> cycle -> device -> practitioner -> procedure
 * -> pseudonymized patient" (CLAUDE.md). "usages" is explicitly named in
 * CLAUDE.md invariant #7's evidence-table list — append-only, no delete
 * route, no SoftDeletes: a mistaken entry is corrected via a
 * non-conformity (S4-ANA-05), never by editing or removing this row.
 * unique(label_id): a pouch is used exactly once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('label_usages', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('label_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignUuid('patient_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('practitioner_id')->constrained('users')->restrictOnDelete();
            $table->string('procedure');
            $table->timestampTz('used_at');
            $table->text('notes')->nullable();
            $table->timestampTz('created_at');

            $table->index(['tenant_id', 'patient_id']);
            $table->index(['tenant_id', 'practitioner_id']);
        });

        DB::statement(<<<'SQL'
            CREATE TRIGGER label_usages_no_update_delete
                BEFORE UPDATE OR DELETE ON label_usages
                FOR EACH ROW EXECUTE FUNCTION prevent_append_only_table_modification()
            SQL);

        DB::statement('ALTER TABLE label_usages ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE label_usages FORCE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON label_usages
                FOR SELECT
                USING (tenant_id = current_setting('app.tenant_id', true)::uuid)
            SQL);
        DB::statement(<<<'SQL'
            CREATE POLICY allow_insert ON label_usages
                FOR INSERT
                WITH CHECK (true)
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS label_usages_no_update_delete ON label_usages');
        Schema::dropIfExists('label_usages');
    }
};
