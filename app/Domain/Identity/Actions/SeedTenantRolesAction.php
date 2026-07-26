<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\TenantRole;
use App\Domain\Tenancy\Models\Tenant;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The six roles from CLAUDE.md, seeded per tenant at creation. Permissions
 * only cover what actually exists today (tenancy/identity) — adding
 * permissions for catalogue/inventory/cycles ahead of those domains
 * shipping would be exactly the kind of premature scaffolding CLAUDE.md
 * says not to do. Extend PERMISSIONS_BY_ROLE as each domain lands.
 */
class SeedTenantRolesAction
{
    private const array PERMISSIONS = [
        'invitations.create',
        'invitations.revoke',
        'memberships.disable',
        'sites.view',
        'audit.view',
        'products.view',
        'products.manage',
        'suppliers.view',
        'suppliers.manage',
        'purchasing.view',
        'purchasing.manage',
        'inventory.view',
        'inventory.manage',
        'alerts.view',
        'alerts.manage',
        'devices.view',
        'devices.manage',
        'cycles.view',
        'cycles.manage',
        'cycles.release',
        'labels.view',
        'labels.manage',
        'patients.view',
        'patients.manage',
        'usages.view',
        'usages.manage',
        'exports.manage',
    ];

    private const array PERMISSIONS_BY_ROLE = [
        'owner' => self::PERMISSIONS,
        'admin' => self::PERMISSIONS,
        // "Catalogue, suppliers, orders, receipts, movements, batches,
        // inventories, alerts" (CLAUDE.md) — stock_manager manages the
        // catalogue, suppliers, purchase orders, stock, and alerts;
        // everyone else just reads them. Equipment (devices/programs/
        // maintenance) isn't named for a specific role in CLAUDE.md either,
        // so it follows the same operational-master-data pattern.
        'stock_manager' => [
            'sites.view', 'products.view', 'products.manage',
            'suppliers.view', 'suppliers.manage', 'purchasing.view', 'purchasing.manage',
            'inventory.view', 'inventory.manage', 'alerts.view', 'alerts.manage',
            'devices.view', 'devices.manage', 'cycles.view', 'cycles.manage',
            'labels.view', 'labels.manage', 'patients.view', 'usages.view',
        ],
        // releaser is the only role besides owner/admin with cycles.release
        // — running a cycle (cycles.manage) and releasing it are kept
        // distinct even though the same fixed role list has to cover both.
        'releaser' => ['sites.view', 'products.view', 'suppliers.view', 'purchasing.view', 'inventory.view', 'alerts.view', 'devices.view', 'cycles.view', 'cycles.release', 'labels.view', 'patients.view', 'usages.view'],
        // practitioner is the only non-owner/admin role with patients.manage
        // and usages.manage — they're the one creating a pseudonym and
        // recording label->procedure->patient while treating a patient.
        'practitioner' => ['sites.view', 'products.view', 'suppliers.view', 'purchasing.view', 'inventory.view', 'alerts.view', 'devices.view', 'cycles.view', 'labels.view', 'patients.view', 'patients.manage', 'usages.view', 'usages.manage'],
        'viewer' => ['sites.view', 'products.view', 'suppliers.view', 'purchasing.view', 'inventory.view', 'alerts.view', 'devices.view', 'cycles.view', 'labels.view', 'patients.view', 'usages.view'],
    ];

    public function execute(Tenant $tenant): void
    {
        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($tenant->id);

        try {
            foreach (self::PERMISSIONS as $permissionName) {
                Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
            }

            foreach (TenantRole::cases() as $role) {
                $roleModel = Role::firstOrCreate([
                    'name' => $role->value,
                    'guard_name' => 'web',
                    'tenant_id' => $tenant->id,
                ]);

                $roleModel->syncPermissions(self::PERMISSIONS_BY_ROLE[$role->value]);
            }
        } finally {
            $registrar->setPermissionsTeamId($previousTeamId);
        }
    }
}
