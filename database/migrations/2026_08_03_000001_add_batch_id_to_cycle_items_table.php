<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The missing half of "receive stock (batch+expiry) -> prepare a load"
 * (CLAUDE.md) — cycle_items shipped in S3-ANA-02 without a link back to
 * the inventory batch it was packed from, which S4-ANA-03's "search by
 * ... batch" surfaces as a real gap. Nullable: not every load item is
 * necessarily a batch-tracked consumable (e.g. reusable instruments with
 * no Inventory batch at all), so this is additive, not a hard requirement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cycle_items', function (Blueprint $table) {
            $table->foreignUuid('batch_id')->nullable()->after('cycle_id')->constrained()->nullOnDelete();
            // S4-ANA-03 searches by batch — this FK column has no index
            // otherwise (unlike cycle_id, which is covered by the
            // existing (cycle_id, sequence_in_cycle) unique constraint).
            $table->index('batch_id');
        });
    }

    public function down(): void
    {
        Schema::table('cycle_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('batch_id');
        });
    }
};
