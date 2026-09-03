<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_portal_accounts', function (Blueprint $table) {
            $table->dropColumn(['activation_token', 'activation_expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('patient_portal_accounts', function (Blueprint $table) {
            $table->string('activation_token', 64)->nullable()->unique();
            $table->timestamp('activation_expires_at')->nullable();
        });
    }
};
