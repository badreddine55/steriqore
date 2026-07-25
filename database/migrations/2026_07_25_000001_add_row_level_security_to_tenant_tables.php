<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The second line of defence behind the ORM's TenantScope (see
 * App\Support\Tenancy\TenantScope): even a forgotten scope, a raw query, or
 * a bug can't leak across tenants, because Postgres itself refuses the row.
 *
 * Scope note: this covers sites/rooms/storage_locations only — the tables
 * S1-ANA-01 owns. tenant_user, invitations, and personal_access_tokens carry
 * a tenant_id too but are *resolved during* authentication (you must read a
 * tenant_user row to find out which tenant a login belongs to, before any
 * "current tenant" exists to scope by) — RLS on those needs a policy shaped
 * around that bootstrap lookup, which is S1-ANA-02's job when the
 * invitation/session flows are actually built, not guessed at here.
 *
 * FORCE ROW LEVEL SECURITY matters: without it, the table owner (the
 * elevated migrations role) bypasses RLS by default, silently defeating it.
 * The running application must connect as a different, non-superuser,
 * non-owner role for any of this to actually apply — see
 * docker/postgres/init/01-create-app-role.sql and config/database.php.
 */
return new class extends Migration
{
    private const TENANT_TABLES = ['sites', 'rooms', 'storage_locations'];

    public function up(): void
    {
        foreach (self::TENANT_TABLES as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");

            DB::statement(<<<SQL
                CREATE POLICY tenant_isolation ON {$table}
                    USING (tenant_id = current_setting('app.tenant_id', true)::uuid)
                    WITH CHECK (tenant_id = current_setting('app.tenant_id', true)::uuid)
                SQL);
        }
    }

    public function down(): void
    {
        foreach (self::TENANT_TABLES as $table) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
            DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }
    }
};
