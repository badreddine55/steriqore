export type User = {
    id: string;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
};

export type Auth = {
    user: User;
    /** Every permission the user holds for the current tenant (empty on guest pages). */
    permissions: string[];
    /** The user's single role within the current tenant — owner/admin/stock_manager/releaser/practitioner/viewer. */
    role: string | null;
};

export type Tenant = {
    id: string;
    name: string;
    slug: string;
};

export type Site = {
    id: string;
    name: string;
    timezone: string;
    is_primary: boolean;
};

/* @chisel-passkeys */
export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};
/* @end-chisel-passkeys */

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
