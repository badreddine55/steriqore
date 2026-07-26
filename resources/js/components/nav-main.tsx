import { Link, usePage } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavGroup } from '@/types';

/**
 * Permission-aware: an item the user can't access is not rendered at all
 * (hiding is UX only — every page's own Policy is still the real
 * authorization). A group with no visible items renders nothing.
 */
export function NavMain({ groups }: { groups: NavGroup[] }) {
    const { isCurrentUrl } = useCurrentUrl();
    const { auth } = usePage().props;
    const permissions = auth.permissions;

    return (
        <>
            {groups.map((group) => {
                const visibleItems = group.items.filter(
                    (item) =>
                        !item.permission ||
                        permissions.includes(item.permission),
                );

                if (visibleItems.length === 0) {
                    return null;
                }

                return (
                    <SidebarGroup key={group.label} className="px-2 py-0">
                        <SidebarGroupLabel>{group.label}</SidebarGroupLabel>
                        <SidebarMenu>
                            {visibleItems.map((item) =>
                                item.comingSoon ? (
                                    <SidebarMenuItem key={item.title}>
                                        <SidebarMenuButton
                                            disabled
                                            tooltip={{
                                                children: `${item.title} — coming soon`,
                                            }}
                                        >
                                            {item.icon && <item.icon />}
                                            <span>{item.title}</span>
                                            <Badge
                                                variant="outline"
                                                className="ml-auto text-[10px] font-normal opacity-60"
                                            >
                                                Soon
                                            </Badge>
                                        </SidebarMenuButton>
                                    </SidebarMenuItem>
                                ) : (
                                    <SidebarMenuItem key={item.title}>
                                        <SidebarMenuButton
                                            asChild
                                            isActive={isCurrentUrl(item.href)}
                                            tooltip={{ children: item.title }}
                                        >
                                            <Link href={item.href} prefetch>
                                                {item.icon && <item.icon />}
                                                <span>{item.title}</span>
                                            </Link>
                                        </SidebarMenuButton>
                                    </SidebarMenuItem>
                                ),
                            )}
                        </SidebarMenu>
                    </SidebarGroup>
                );
            })}
        </>
    );
}
