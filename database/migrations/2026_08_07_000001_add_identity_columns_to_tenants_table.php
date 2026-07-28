<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UI-28 practice identity section. Mirrors Site's own address/timezone
 * column shapes exactly. Not evidence-affecting (not versioned) — a plain
 * audited update (old/new values + reason) is enough, per the brief's own
 * categorization. Logo is a spatie/laravel-medialibrary collection (see
 * Tenant::registerMediaCollections()), not a column here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('legal_name')->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('city')->nullable();
            $table->char('country_code', 2)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('timezone')->default('Europe/Paris');
            $table->string('locale')->default('fr');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'legal_name', 'address_line1', 'address_line2', 'postal_code',
                'city', 'country_code', 'contact_email', 'contact_phone',
                'timezone', 'locale',
            ]);
        });
    }
};
