<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // citext: case-insensitive text, used for email/slug columns so
        // Foo@x.com and foo@x.com are always the same row.
        DB::statement('CREATE EXTENSION IF NOT EXISTS citext');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP EXTENSION IF EXISTS citext');
    }
};
