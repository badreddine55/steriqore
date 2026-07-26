<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S4-ANA-03's evidence search filters and sorts by a used_at date range —
 * deliberate indexing per CLAUDE.md's "evidence search < 2s at pilot
 * volume" requirement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('label_usages', function (Blueprint $table) {
            $table->index(['tenant_id', 'used_at']);
        });
    }

    public function down(): void
    {
        Schema::table('label_usages', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'used_at']);
        });
    }
};
