import { Link, router, usePage } from '@inertiajs/react';
import { Building2, LogOut, Settings } from 'lucide-react';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { tenantRoleLabel } from '@/lib/status';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import tenant from '@/routes/tenant';
import type { User } from '@/types';

type Props = {
    user: User;
};

export function UserMenuContent({ user }: Props) {
    const cleanup = useMobileNavigation();
    const page = usePage();
    const currentTenant = page.props.tenant;
    const tenants = page.props.tenants;
    const role = page.props.auth.role;

    const handleLogout = () => {
        cleanup();
        router.flushAll();
    };

    const switchTenant = (tenantId: string) => {
        router.post(tenant.switch.url(), { tenant_id: tenantId });
    };

    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                    <UserInfo user={user} showEmail={true} />
                </div>
            </DropdownMenuLabel>
            {currentTenant && (
                <>
                    <DropdownMenuSeparator />
                    <div className="px-2 py-1 text-xs text-muted-foreground">
                        {currentTenant.name}
                        {role && ` · ${tenantRoleLabel[role] ?? role}`}
                    </div>
                    {tenants.length > 1 && (
                        <DropdownMenuGroup>
                            {tenants
                                .filter((t) => t.id !== currentTenant.id)
                                .map((t) => (
                                    <DropdownMenuItem
                                        key={t.id}
                                        className="cursor-pointer"
                                        onClick={() => switchTenant(t.id)}
                                    >
                                        <Building2 className="mr-2" />
                                        Switch to {t.name}
                                    </DropdownMenuItem>
                                ))}
                        </DropdownMenuGroup>
                    )}
                </>
            )}
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={edit()}
                        prefetch
                        onClick={cleanup}
                    >
                        <Settings className="mr-2" />
                        Settings
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
                <Link
                    className="block w-full cursor-pointer"
                    href={logout()}
                    as="button"
                    onClick={handleLogout}
                    data-test="logout-button"
                >
                    <LogOut className="mr-2" />
                    Log out
                </Link>
            </DropdownMenuItem>
        </>
    );
}
