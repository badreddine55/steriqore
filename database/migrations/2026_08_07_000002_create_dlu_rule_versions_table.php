<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * UI-28: the DLU rule table must be versioned, never overwritten in place
 * (a label's use-by date is frozen at generation time — CLAUDE.md — but
 * until now there was no durable, immutable row a dossier could point back
 * to as "the rule version that applied"). dlu_rules stays exactly as it
 * was — the fast "current" projection every existing query already reads
 * (GenerateCycleLabelsAction, the settings UI) — this is the append-only
 * ledger behind it, one row per change, mirroring cycle_releases/
 * label_prints' trigger-enforced immutability. Every dlu_rules row now
 * also points at its own current_version_id, and every Label freezes
 * which version produced its use-by date via dlu_rule_version_id — the
 * evidentiary link the dossier needs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dlu_rule_versions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('packaging_type');
            $table->string('storage_condition');
            $table->unsignedInteger('shelf_life_days');
            $table->foreignUuid('actor_id')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->timestampTz('effective_from');
            $table->timestampTz('created_at');

            $table->index(['tenant_id', 'packaging_type', 'storage_condition', 'effective_from']);
        });

        DB::statement(<<<'SQL'
            CREATE TRIGGER dlu_rule_versions_no_update_delete
                BEFORE UPDATE OR DELETE ON dlu_rule_versions
                FOR EACH ROW EXECUTE FUNCTION prevent_append_only_table_modification()
            SQL);

        DB::statement('ALTER TABLE dlu_rule_versions ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE dlu_rule_versions FORCE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON dlu_rule_versions
                FOR SELECT
                USING (tenant_id = current_setting('app.tenant_id', true)::uuid)
            SQL);
        DB::statement(<<<'SQL'
            CREATE POLICY allow_insert ON dlu_rule_versions
                FOR INSERT
                WITH CHECK (true)
            SQL);

        Schema::table('dlu_rules', function (Blueprint $table) {
            $table->foreignUuid('current_version_id')->nullable()->after('shelf_life_days')
                ->constrained('dlu_rule_versions')->nullOnDelete();
        });

        Schema::table('labels', function (Blueprint $table) {
            $table->foreignUuid('dlu_rule_version_id')->nullable()->after('shelf_life_days')
                ->constrained('dlu_rule_versions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('labels', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dlu_rule_version_id');
        });

        Schema::table('dlu_rules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_version_id');
        });

        DB::statement('DROP TRIGGER IF EXISTS dlu_rule_versions_no_update_delete ON dlu_rule_versions');
        Schema::dropIfExists('dlu_rule_versions');
    }
};
