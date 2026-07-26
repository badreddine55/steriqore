<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cycle_items', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('cycle_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->unsignedInteger('sequence_in_cycle');
            $table->timestamps();

            $table->unique(['cycle_id', 'sequence_in_cycle']);
        });

        DB::statement('ALTER TABLE cycle_items ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE cycle_items FORCE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON cycle_items
                USING (tenant_id = current_setting('app.tenant_id', true)::uuid)
                WITH CHECK (tenant_id = current_setting('app.tenant_id', true)::uuid)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('cycle_items');
    }
};
