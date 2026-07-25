<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Same second line of defence as
 * database/migrations/2026_07_25_000001_add_row_level_security_to_tenant_tables.php,
 * for the tenant-owned spatie/laravel-permission tables (config/permission.php
 * has teams enabled, team_foreign_key = tenant_id). `permissions` and
 * `role_has_permissions` are deliberately excluded — permissions are global
 * definitions ("sites.view"), not tenant data, and role_has_permissions has
 * no tenant_id of its own (it's protected transitively: a role_id only ever
 * belongs to one tenant, enforced by `roles`' own RLS).
 */
return new class extends Migration
{
    private const TENANT_TABLES = ['roles', 'model_has_roles', 'model_has_permissions'];

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
