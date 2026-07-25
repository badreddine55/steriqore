<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('parent_category_id')->nullable();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'name']);
        });

        Schema::table('product_categories', function (Blueprint $table) {
            $table->foreign('parent_category_id')->references('id')->on('product_categories')->nullOnDelete();
        });

        DB::statement('ALTER TABLE product_categories ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE product_categories FORCE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON product_categories
                USING (tenant_id = current_setting('app.tenant_id', true)::uuid)
                WITH CHECK (tenant_id = current_setting('app.tenant_id', true)::uuid)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('product_categories');
    }
};
