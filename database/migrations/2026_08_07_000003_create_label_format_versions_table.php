<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * UI-28: label format (sheet layout, which 2D code is visually primary,
 * optional extra printed fields) is versioned the same way as DLU rules —
 * see 2026_08_07_000002's docblock. QR and DataMatrix are both always
 * printed on every label (CLAUDE.md's LABELS section); primary_code only
 * controls which is emphasized, it's never an either/or codec choice, and
 * changing it never touches the label's own opaque payload.
 *
 * This is a print-time rendering concern, not a label-creation-time one
 * (unlike the DLU rule, which is frozen once at generation) — so the
 * version actually used is recorded per print EVENT, on label_prints, not
 * on labels itself. A label's own printed appearance can legitimately
 * differ between its first print and a later reprint if the format
 * changed in between; the brief is explicit that's fine ("applies to
 * future prints only").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('label_format_versions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('primary_code')->default('datamatrix');
            $table->string('sheet_layout')->default('a4_24');
            $table->boolean('show_batch_number')->default(false);
            $table->boolean('show_product_name')->default(false);
            $table->foreignUuid('actor_id')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->timestampTz('effective_from');
            $table->timestampTz('created_at');

            $table->index(['tenant_id', 'effective_from']);
        });

        DB::statement(<<<'SQL'
            CREATE TRIGGER label_format_versions_no_update_delete
                BEFORE UPDATE OR DELETE ON label_format_versions
                FOR EACH ROW EXECUTE FUNCTION prevent_append_only_table_modification()
            SQL);

        DB::statement('ALTER TABLE label_format_versions ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE label_format_versions FORCE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON label_format_versions
                FOR SELECT
                USING (tenant_id = current_setting('app.tenant_id', true)::uuid)
            SQL);
        DB::statement(<<<'SQL'
            CREATE POLICY allow_insert ON label_format_versions
                FOR INSERT
                WITH CHECK (true)
            SQL);

        Schema::table('label_prints', function (Blueprint $table) {
            $table->foreignUuid('label_format_version_id')->nullable()->after('sequence')
                ->constrained('label_format_versions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('label_prints', function (Blueprint $table) {
            $table->dropConstrainedForeignId('label_format_version_id');
        });

        DB::statement('DROP TRIGGER IF EXISTS label_format_versions_no_update_delete ON label_format_versions');
        Schema::dropIfExists('label_format_versions');
    }
};
