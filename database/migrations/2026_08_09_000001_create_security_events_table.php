<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A dedicated, operator-only security channel — deliberately NOT
 * audit_events. audit_events is the tenant-visible evidence trail (RLS
 * lets a practice read its OWN rows); a cross-tenant probe, an
 * authorization failure, or an RLS policy rejection belongs to neither the
 * practice that was probed nor the one that did the probing — it's a
 * platform-security signal, only ever meaningful in aggregate/rate-spike
 * form to whoever operates the platform. So: no tenant_id column that
 * "owns" the row (a single row can legitimately reference TWO tenants —
 * claimed and actual — which doesn't fit the BelongsToTenant model at
 * all), RLS enabled with NO permissive policy for the app's own
 * `steriqore_app` role (default-deny, both read and write), and every
 * access — from RecordSecurityEventAction's writes onward — goes through
 * the `pgsql_admin` connection instead (App\Domain\Compliance\Models\
 * SecurityEvent pins its own $connection), the same RLS-bypassing
 * connection SeedPilotVolume/GenerateDataExportJob already use for
 * legitimate cross-tenant admin access. That also sidesteps a subtler
 * problem: an RLS policy rejection means the app's own connection/
 * transaction is already aborted by Postgres at the moment we need to
 * record it — writing through a wholly separate connection is the only
 * way that write can succeed at all.
 *
 * Append-only like every other evidence/security table in this app: no
 * update/delete route, and the DB itself refuses regardless of role.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_events', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->string('type');
            $table->uuid('actor_id')->nullable();
            $table->string('actor_label_snapshot')->nullable();
            $table->uuid('tenant_claimed_id')->nullable();
            $table->uuid('tenant_actual_id')->nullable();
            $table->string('route_name')->nullable();
            $table->string('method');
            $table->string('path');
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->ipAddress('ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->uuid('request_id');
            $table->timestampTz('occurred_at');

            // No FKs: actor/tenant ids here are read back from a live
            // request (or, for tenant_actual_id, a cross-tenant lookup) —
            // this table must never fail to record an event because a
            // referenced row got cleaned up, and a garbage/spoofed id in
            // a probe attempt is exactly the kind of thing worth keeping
            // even if it doesn't resolve to anything real.
            $table->index(['type', 'occurred_at']);
            $table->index(['tenant_claimed_id', 'occurred_at']);
            $table->index('actor_id');
        });

        DB::statement(<<<'SQL'
            CREATE TRIGGER security_events_no_update_delete
                BEFORE UPDATE OR DELETE ON security_events
                FOR EACH ROW EXECUTE FUNCTION prevent_append_only_table_modification()
            SQL);

        // No policies at all, deliberately: RLS-with-no-applicable-policy
        // is default-deny for every command, for every role subject to it.
        // The `steriqore_app` role (what every tenant-facing request runs
        // as) is subject to RLS and has no policy here, so it can neither
        // read nor write this table under any circumstance — only the
        // `pgsql_admin` connection (bypasses RLS entirely, see class docblock)
        // can, and only App\Domain\Compliance\Models\SecurityEvent uses it.
        DB::statement('ALTER TABLE security_events ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE security_events FORCE ROW LEVEL SECURITY');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS security_events_no_update_delete ON security_events');
        Schema::dropIfExists('security_events');
    }
};
