import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
    /** Permission required to see this item; omit for always-visible items (e.g. personal settings). */
    permission?: string;
    /** A planned screen with no backend page yet — rendered so the intended structure is visible, but disabled/non-navigable. */
    comingSoon?: boolean;
};

export type NavGroup = {
    label: string;
    items: NavItem[];
};
