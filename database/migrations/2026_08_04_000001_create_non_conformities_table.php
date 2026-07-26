<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CLAUDE.md invariant #3: "A cycle_release row is immutable. Mistakes are
 * corrected by a non_conformity + recall, never by UPDATE." This is that
 * mechanism. subject_type/subject_id is polymorphic (Cycle or Label —
 * raising one against a cycle recalls every label it produced; against a
 * label recalls just that one pouch). Not itself an append-only evidence
 * table (unlike cycles/releases/labels/usages/movements/audit_events,
 * CLAUDE.md invariant #7's named list) — resolving one is a normal update
 * (resolved_at/resolved_by/resolution), the way Alert already works.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('non_conformities', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type');
            $table->uuid('subject_id');
            $table->text('description');
            $table->foreignUuid('raised_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('raised_at');
            $table->foreignUuid('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('resolved_at')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'subject_type', 'subject_id']);
        });

        DB::statement('ALTER TABLE non_conformities ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE non_conformities FORCE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON non_conformities
                USING (tenant_id = current_setting('app.tenant_id', true)::uuid)
                WITH CHECK (tenant_id = current_setting('app.tenant_id', true)::uuid)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('non_conformities');
    }
};
