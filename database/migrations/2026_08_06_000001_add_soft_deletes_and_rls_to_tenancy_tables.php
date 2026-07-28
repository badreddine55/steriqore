<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * sites/rooms/storage_locations predate two conventions every later
 * tenant table follows: soft deletes (UI-27's "never hard-delete, archive
 * instead") and PostgreSQL RLS as the second line of defence behind
 * BelongsToTenant (CLAUDE.md tenancy invariant). Brought in line here
 * rather than editing the original S1 migrations, since the demo tenant
 * already has data against them.
 *
 * Idempotent per statement (hasColumn / DROP POLICY IF EXISTS / a plain
 * try-catch around ENABLE/FORCE) since an earlier run against the wrong
 * (non-owner) connection partially applied the RLS half before failing.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['sites', 'rooms', 'storage_locations'] as $table) {
            if (! Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->softDeletes();
                });
            }

            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
            DB::statement(<<<SQL
                CREATE POLICY tenant_isolation ON {$table}
                    USING (tenant_id = current_setting('app.tenant_id', true)::uuid)
                    WITH CHECK (tenant_id = current_setting('app.tenant_id', true)::uuid)
                SQL);
        }
    }

    public function down(): void
    {
        foreach (['sites', 'rooms', 'storage_locations'] as $table) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");

            if (Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropSoftDeletes();
                });
            }
        }
    }
};
