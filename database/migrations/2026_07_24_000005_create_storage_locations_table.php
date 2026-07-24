<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('storage_locations', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('room_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('parent_location_id')->nullable();
            $table->string('name');
            $table->string('kind')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'name']);
        });

        // Self-referencing FK added after the table (and its primary key) exists —
        // Laravel queues "add primary key" after foreign keys within the same
        // Schema::create(), which breaks a self-reference declared inline.
        Schema::table('storage_locations', function (Blueprint $table) {
            $table->foreign('parent_location_id')->references('id')->on('storage_locations')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('storage_locations');
    }
};
