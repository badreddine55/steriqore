<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('reference');
            $table->string('last_name')->nullable()->after('first_name');
            $table->date('birth_date')->nullable()->after('last_name');
            $table->string('email')->nullable()->after('birth_date');
            $table->string('phone')->nullable()->after('email');
            $table->string('nir')->nullable()->after('phone');
            $table->text('notes')->nullable()->after('nir');
            $table->json('allergies')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn([
                'first_name',
                'last_name',
                'birth_date',
                'email',
                'phone',
                'nir',
                'notes',
                'allergies',
            ]);
        });
    }
};
