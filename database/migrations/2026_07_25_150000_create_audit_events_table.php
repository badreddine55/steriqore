<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only, twice over: no UPDATE/DELETE route in the app (invariant #7,
 * CLAUDE.md), and — because that alone only stops the app, not a bug or a
 * direct psql session — a trigger that raises on any UPDATE/DELETE
 * regardless of who's connected, including a superuser. RLS (below) is
 * bypassed by superusers/table owners; this trigger is not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_label_snapshot')->nullable();
            $table->string('action');
            $table->string('subject_type');
            $table->uuid('subject_id');
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->text('reason')->nullable();
            $table->ipAddress('ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->uuid('request_id');
            $table->timestampTz('occurred_at');

            $table->index(['tenant_id', 'occurred_at']);
            $table->index(['subject_type', 'subject_id']);
        });

        // OR REPLACE: `migrate:fresh`/db:wipe drops tables via CASCADE but
        // leaves standalone functions behind, so a plain CREATE FUNCTION
        // fails as "already exists" on the second fresh migration in the
        // same database.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_audit_events_modification() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'audit_events is append-only: % is not permitted', TG_OP;
            END;
            $$ LANGUAGE plpgsql
            SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER audit_events_no_update_delete
                BEFORE UPDATE OR DELETE ON audit_events
                FOR EACH ROW EXECUTE FUNCTION prevent_audit_events_modification()
            SQL);

        // SELECT-only policy, deliberately: some audit events are written
        // during bootstrap flows (login, registration, invitation accept)
        // that run before the `tenant` middleware has set app.tenant_id —
        // there's no "current tenant" transaction to check an insert
        // against yet. Writes are trusted the same way every other
        // Eloquent create() in the app is (only RecordAuditEventAction
        // calls this, never raw client input); reads — the actual leak risk,
        // one practice seeing another's audit trail — are enforced here.
        DB::statement('ALTER TABLE audit_events ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE audit_events FORCE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON audit_events
                FOR SELECT
                USING (tenant_id = current_setting('app.tenant_id', true)::uuid)
            SQL);

        // Without this, RLS-with-no-applicable-policy means "deny all" for
        // INSERT too (not "allow all") — this makes that explicit rather
        // than accidentally relying on the absence of a restriction.
        DB::statement(<<<'SQL'
            CREATE POLICY allow_insert ON audit_events
                FOR INSERT
                WITH CHECK (true)
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS audit_events_no_update_delete ON audit_events');
        DB::statement('DROP FUNCTION IF EXISTS prevent_audit_events_modification()');
        Schema::dropIfExists('audit_events');
    }
};
