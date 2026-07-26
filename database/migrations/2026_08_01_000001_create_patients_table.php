<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pseudonymized only, by design: CLAUDE.md — "Patient data is pseudonymous
 * only; NO real patient data until a written GDPR/HDS decision exists."
 * `reference` is a system-generated opaque label (e.g. "PAT-000123"), not
 * a name/DOB/anything identifying. The practice maps it back to their own
 * records externally; SteriQore never stores that mapping. No further
 * fields on purpose — this is the minimum-data decision, not a starting
 * point to grow from without a new written decision.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('reference');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'reference']);
        });

        DB::statement('ALTER TABLE patients ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE patients FORCE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON patients
                USING (tenant_id = current_setting('app.tenant_id', true)::uuid)
                WITH CHECK (tenant_id = current_setting('app.tenant_id', true)::uuid)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
