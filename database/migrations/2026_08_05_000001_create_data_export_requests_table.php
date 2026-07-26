<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S6: "full data+file portability export" — a tenant's complete data
 * (every tenant-owned table, introspected via information_schema rather
 * than a hardcoded list, so it can never silently miss a table added
 * later) plus every file they own on the 'media' disk, as one
 * downloadable zip. Queued (GenerateDataExportJob) since a real tenant's
 * full history can be large — this table tracks the request/result.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_export_requests', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('status')->default('pending');
            $table->string('file_path')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        DB::statement('ALTER TABLE data_export_requests ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE data_export_requests FORCE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON data_export_requests
                USING (tenant_id = current_setting('app.tenant_id', true)::uuid)
                WITH CHECK (tenant_id = current_setting('app.tenant_id', true)::uuid)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('data_export_requests');
    }
};
