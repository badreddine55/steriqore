<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('control_tests', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('cycle_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('result');
            $table->timestamp('performed_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'cycle_id']);
        });

        // Evidence table — the control-test logbook (CLAUDE.md). No delete
        // route, no SoftDeletes: a mistaken entry is corrected by recording
        // a new test, never by editing or removing the old one.
        DB::statement('ALTER TABLE control_tests ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE control_tests FORCE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON control_tests
                USING (tenant_id = current_setting('app.tenant_id', true)::uuid)
                WITH CHECK (tenant_id = current_setting('app.tenant_id', true)::uuid)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('control_tests');
    }
};
