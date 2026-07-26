<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('site_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('serial_number');
            $table->string('kind')->default('autoclave');
            $table->string('status')->default('active');
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->date('commissioned_at')->nullable();
            $table->date('decommissioned_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'serial_number']);
        });

        DB::statement('ALTER TABLE devices ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE devices FORCE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON devices
                USING (tenant_id = current_setting('app.tenant_id', true)::uuid)
                WITH CHECK (tenant_id = current_setting('app.tenant_id', true)::uuid)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
