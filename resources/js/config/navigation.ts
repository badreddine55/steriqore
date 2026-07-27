import {
    Bell,
    Boxes,
    Building2,
    ClipboardCheck,
    ClipboardList,
    FlaskConical,
    KeyRound,
    LayoutGrid,
    Layers,
    PackageSearch,
    PackageX,
    Palette,
    ScrollText,
    Search,
    ShieldAlert,
    Tags,
    Truck,
    UserCircle,
    UserRoundSearch,
    Users,
    Wrench,
} from 'lucide-react';
import { dashboard } from '@/routes';
import alertRoutes from '@/routes/alerts';
import { edit as editAppearance } from '@/routes/appearance';
import auditLogRoutes from '@/routes/audit-log';
import batchRoutes from '@/routes/batches';
import controlTests from '@/routes/control-tests';
import cycles from '@/routes/cycles';
import devices from '@/routes/devices';
import evidenceSearchRoutes from '@/routes/evidence-search';
import labels from '@/routes/labels';
import nonConformityRoutes from '@/routes/non-conformities';
import patientRoutes from '@/routes/patients';
import productRoutes from '@/routes/products';
import { edit as editProfile } from '@/routes/profile';
import purchaseOrderRoutes from '@/routes/purchase-orders';
import quarantineRoutes from '@/routes/quarantine';
import { edit as editSecurity } from '@/routes/security';
import stockRoutes from '@/routes/stock';
import supplierRoutes from '@/routes/suppliers';
import type { NavGroup, NavItem } from '@/types';

/**
 * Grouped by job, not by database table (CLAUDE.md/S7 direction). Most
 * items below are marked `comingSoon` — the screen doesn't exist yet
 * (only Cycles does, S7's first vertical slice) — they're still rendered
 * so the intended information architecture is visible for review, just
 * disabled rather than linking to a 404. `permission` mirrors the exact
 * permission string each screen's Policy will check server-side; hiding
 * a nav item is UX only, never the actual authorization.
 */
export const topNavItem: NavItem = {
    title: 'Dashboard',
    href: dashboard(),
    icon: LayoutGrid,
};

export const navGroups: NavGroup[] = [
    {
        label: 'Sterilization',
        items: [
            {
                title: 'Cycles',
                href: cycles.index(),
                icon: FlaskConical,
                permission: 'cycles.view',
            },
            {
                title: 'Control tests',
                href: controlTests.index(),
                icon: ClipboardCheck,
                permission: 'cycles.view',
            },
            {
                title: 'Devices',
                href: devices.index(),
                icon: Wrench,
                permission: 'devices.view',
            },
            {
                title: 'Labels',
                href: labels.index(),
                icon: Tags,
                permission: 'labels.view',
            },
        ],
    },
    {
        label: 'Inventory',
        items: [
            {
                title: 'Products',
                href: productRoutes.index(),
                icon: Boxes,
                permission: 'products.view',
            },
            {
                title: 'Suppliers',
                href: supplierRoutes.index(),
                icon: Truck,
                permission: 'suppliers.view',
            },
            {
                title: 'Purchase orders',
                href: purchaseOrderRoutes.index(),
                icon: ClipboardList,
                permission: 'purchasing.view',
            },
            {
                title: 'Batches',
                href: batchRoutes.index(),
                icon: Layers,
                permission: 'inventory.view',
            },
            {
                title: 'Stock',
                href: stockRoutes.index(),
                icon: PackageSearch,
                permission: 'inventory.view',
            },
            {
                title: 'Alerts',
                href: alertRoutes.index(),
                icon: Bell,
                permission: 'alerts.view',
            },
        ],
    },
    {
        label: 'Traceability',
        items: [
            {
                title: 'Patients',
                href: patientRoutes.index(),
                icon: UserRoundSearch,
                permission: 'patients.view',
            },
            {
                title: 'Evidence search',
                href: evidenceSearchRoutes.index(),
                icon: Search,
                permission: 'usages.view',
            },
        ],
    },
    {
        label: 'Compliance',
        items: [
            {
                title: 'Non-conformities',
                href: nonConformityRoutes.index(),
                icon: ShieldAlert,
                permission: 'non_conformities.view',
            },
            {
                title: 'Quarantine',
                href: quarantineRoutes.index(),
                icon: PackageX,
                permission: 'inventory.view',
            },
            {
                title: 'Audit log',
                href: auditLogRoutes.index(),
                icon: ScrollText,
                permission: 'audit.view',
            },
        ],
    },
    {
        label: 'Settings',
        items: [
            {
                title: 'Team',
                href: '/team',
                icon: Users,
                permission: 'invitations.create',
                comingSoon: true,
            },
            {
                title: 'Sites',
                href: '/sites',
                icon: Building2,
                permission: 'sites.view',
                comingSoon: true,
            },
            { title: 'Profile', href: editProfile(), icon: UserCircle },
            { title: 'Security', href: editSecurity(), icon: KeyRound },
            { title: 'Appearance', href: editAppearance(), icon: Palette },
        ],
    },
];
