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
    ];

    private const array PERMISSIONS_BY_ROLE = [
        'owner' => self::PERMISSIONS,
        'admin' => self::PERMISSIONS,
        // "Catalogue, suppliers, orders, receipts, movements, batches,
        // inventories, alerts" (CLAUDE.md) — stock_manager manages the
        // catalogue, suppliers, and purchase orders; everyone else just
        // reads them.
        'stock_manager' => [
            'sites.view', 'products.view', 'products.manage',
            'suppliers.view', 'suppliers.manage', 'purchasing.view', 'purchasing.manage',
        ],
        'releaser' => ['sites.view', 'products.view', 'suppliers.view', 'purchasing.view'],
        'practitioner' => ['sites.view', 'products.view', 'suppliers.view', 'purchasing.view'],
        'viewer' => ['sites.view', 'products.view', 'suppliers.view', 'purchasing.view'],
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
