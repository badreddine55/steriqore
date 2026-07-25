<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->foreignUuid('default_location_id')->nullable()->constrained('storage_locations')->nullOnDelete();
            $table->string('name');
            $table->string('reference');
            $table->string('unit')->default('piece');
            $table->unsignedInteger('min_threshold')->default(0);
            $table->boolean('is_sterilizable')->default(false);
            $table->string('barcode')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'reference']);
            $table->index(['tenant_id', 'barcode']);
        });

        DB::statement('ALTER TABLE products ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE products FORCE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON products
                USING (tenant_id = current_setting('app.tenant_id', true)::uuid)
                WITH CHECK (tenant_id = current_setting('app.tenant_id', true)::uuid)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
