import {
    Bell,
    Boxes,
    Building2,
    ClipboardList,
    FlaskConical,
    KeyRound,
    LayoutGrid,
    PackageSearch,
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
import { edit as editAppearance } from '@/routes/appearance';
import cycles from '@/routes/cycles';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
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
                title: 'Devices',
                href: '/devices',
                icon: Wrench,
                permission: 'devices.view',
                comingSoon: true,
            },
            {
                title: 'Labels',
                href: '/labels',
                icon: Tags,
                permission: 'labels.view',
                comingSoon: true,
            },
        ],
    },
    {
        label: 'Inventory',
        items: [
            {
                title: 'Products',
                href: '/products',
                icon: Boxes,
                permission: 'products.view',
                comingSoon: true,
            },
            {
                title: 'Suppliers',
                href: '/suppliers',
                icon: Truck,
                permission: 'suppliers.view',
                comingSoon: true,
            },
            {
                title: 'Purchase orders',
                href: '/purchase-orders',
                icon: ClipboardList,
                permission: 'purchasing.view',
                comingSoon: true,
            },
            {
                title: 'Stock',
                href: '/stock',
                icon: PackageSearch,
                permission: 'inventory.view',
                comingSoon: true,
            },
            {
                title: 'Alerts',
                href: '/alerts',
                icon: Bell,
                permission: 'alerts.view',
                comingSoon: true,
            },
        ],
    },
    {
        label: 'Traceability',
        items: [
            {
                title: 'Patients',
                href: '/patients',
                icon: UserRoundSearch,
                permission: 'patients.view',
                comingSoon: true,
            },
            {
                title: 'Evidence search',
                href: '/evidence-search',
                icon: Search,
                permission: 'usages.view',
                comingSoon: true,
            },
        ],
    },
    {
        label: 'Compliance',
        items: [
            {
                title: 'Non-conformities',
                href: '/non-conformities',
                icon: ShieldAlert,
                permission: 'non_conformities.view',
                comingSoon: true,
            },
            {
                title: 'Audit log',
                href: '/audit-log',
                icon: ScrollText,
                permission: 'audit.view',
                comingSoon: true,
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
