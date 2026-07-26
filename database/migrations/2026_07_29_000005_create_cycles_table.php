<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cycles', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('device_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('device_program_id')->nullable()->constrained('device_programs')->nullOnDelete();
            $table->foreignUuid('operator_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('cycle_number');
            $table->string('status')->default('draft');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Sequential per device, not globally — matches the "cycle/load
            // number" printed on labels (CLAUDE.md), scoped to the machine
            // that ran it.
            $table->unique(['tenant_id', 'device_id', 'cycle_number']);
            $table->index(['tenant_id', 'status']);
        });

        // Evidence table (CLAUDE.md invariant #7): no delete route is wired
        // in the API, and there is deliberately no SoftDeletes column —
        // a cycle is never removed, only progressed through its states or
        // (later, S3-ANA-03) released/rejected.
        DB::statement('ALTER TABLE cycles ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE cycles FORCE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON cycles
                USING (tenant_id = current_setting('app.tenant_id', true)::uuid)
                WITH CHECK (tenant_id = current_setting('app.tenant_id', true)::uuid)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('cycles');
    }
};
