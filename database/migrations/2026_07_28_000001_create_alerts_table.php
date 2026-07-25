<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * type is deliberately just low_stock|near_expiry|expired for now —
 * failed_cycle and missing_test (CLAUDE.md's full list) need cycles and
 * control tests, which don't exist until S3. Extend DetectAlertsAction's
 * type set then, not before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('severity');
            $table->string('state')->default('open');
            $table->string('subject_type');
            $table->uuid('subject_id');
            $table->text('message');
            $table->foreignUuid('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'state', 'type']);
            // One open alert per (subject, type) at a time — re-detecting
            // doesn't spam duplicates, it just leaves the existing one open.
            $table->unique(['tenant_id', 'type', 'subject_type', 'subject_id', 'state']);
        });

        DB::statement('ALTER TABLE alerts ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE alerts FORCE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON alerts
                USING (tenant_id = current_setting('app.tenant_id', true)::uuid)
                WITH CHECK (tenant_id = current_setting('app.tenant_id', true)::uuid)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
