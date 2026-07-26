<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DLU = "date limite d'utilisation" (use-by date). CLAUDE.md: "DLU comes
 * from a per-tenant rule table (packaging x storage, EN 868 defaults)."
 * No rows are seeded here — the exact EN 868 day counts per
 * packaging/storage combination are a compliance detail this codebase
 * must not invent (see docs/open-questions.md OQ-8). A tenant configures
 * its own rules; printing a label with no matching rule is an explicit,
 * named error, never a guessed default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dlu_rules', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('packaging_type');
            $table->string('storage_condition');
            $table->unsignedInteger('shelf_life_days');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'packaging_type', 'storage_condition']);
        });

        DB::statement('ALTER TABLE dlu_rules ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE dlu_rules FORCE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON dlu_rules
                USING (tenant_id = current_setting('app.tenant_id', true)::uuid)
                WITH CHECK (tenant_id = current_setting('app.tenant_id', true)::uuid)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('dlu_rules');
    }
};
