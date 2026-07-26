import type { Auth, Site, Tenant } from '@/types/auth';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            /** null on guest pages, or for a user with no active practice yet. */
            tenant: Tenant | null;
            /** Every practice the user has active membership in — for the tenant switcher. */
            tenants: Tenant[];
            /** Sites belonging to the current tenant — for the site switcher. */
            sites: Site[];
            currentSiteId: string | null;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
