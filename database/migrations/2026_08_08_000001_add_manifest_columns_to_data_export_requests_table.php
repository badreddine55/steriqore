<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UI-29: the ready-state screen needs a manifest (tables + row counts,
 * file count, total size) so a leaving practice can verify they got
 * everything, and a retention window so "expired" is a real status, not
 * just prose — GenerateDataExportJob populates these at completion time;
 * a new expire scheduled command (UI-29) clears the file + flips the
 * status once expires_at passes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_export_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedInteger('record_count')->nullable();
            $table->unsignedInteger('file_count')->nullable();
            $table->json('table_manifest')->nullable();
            $table->timestamp('expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('data_export_requests', function (Blueprint $table) {
            $table->dropColumn(['size_bytes', 'record_count', 'file_count', 'table_manifest', 'expires_at']);
        });
    }
};
