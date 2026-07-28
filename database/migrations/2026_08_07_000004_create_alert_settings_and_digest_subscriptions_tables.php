<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * UI-28 alerts & notifications section. Not evidence-affecting (not
 * versioned) — a plain audited update (old/new values + reason) is
 * enough. alert_settings is a single row per tenant (unique tenant_id);
 * digest_subscriptions is role-based (which role gets which digest, how
 * often) rather than storing static email addresses, so it stays correct
 * automatically as team membership changes (UI-26) — addresses are
 * resolved at send time from whoever currently holds that role.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_settings', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('near_expiry_days')->default(30);
            $table->unsignedInteger('default_low_stock_threshold')->default(0);
            $table->unsignedInteger('control_test_frequency_days')->nullable();
            $table->unsignedInteger('control_test_overdue_tolerance_days')->nullable();
            $table->unsignedInteger('failed_cycle_escalation_hours')->default(24);
            $table->boolean('digests_enabled')->default(true);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE alert_settings ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE alert_settings FORCE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON alert_settings
                USING (tenant_id = current_setting('app.tenant_id', true)::uuid)
                WITH CHECK (tenant_id = current_setting('app.tenant_id', true)::uuid)
            SQL);

        Schema::create('digest_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->string('frequency')->default('daily');
            $table->boolean('enabled')->default(true);
            $table->timestampTz('last_sent_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'role']);
        });

        DB::statement('ALTER TABLE digest_subscriptions ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE digest_subscriptions FORCE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON digest_subscriptions
                USING (tenant_id = current_setting('app.tenant_id', true)::uuid)
                WITH CHECK (tenant_id = current_setting('app.tenant_id', true)::uuid)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('digest_subscriptions');
        Schema::dropIfExists('alert_settings');
    }
};
