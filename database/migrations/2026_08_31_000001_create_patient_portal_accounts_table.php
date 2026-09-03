<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_portal_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('patient_id')->unique()->constrained('patients')->cascadeOnDelete();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('status')->default('pending_activation'); // pending_activation, active, disabled
            $table->string('activation_token', 64)->nullable()->unique();
            $table->timestamp('activation_expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'email']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_portal_accounts');
    }
};
