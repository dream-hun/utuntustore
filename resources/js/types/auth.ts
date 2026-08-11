import type { SubscriptionStatus, UserRole } from './marketplace';

export type User = {
    /** The public UUID; the auto-increment id never leaves the server. */
    id: string;
    name: string;
    email: string;
    phone?: string | null;
    role?: UserRole;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    two_factor_confirmed_at?: string | null;
    created_at?: string;
    updated_at?: string;
    [key: string]: unknown;
};

/**
 * The vendor context shared on every request for a user who owns a shop.
 *
 * `can_sell` is global because it changes what the whole vendor UI offers: an
 * expired vendor keeps their dashboard but loses the ability to publish, and the
 * interface should say so rather than fail on submit.
 */
export type AuthVendor = {
    id: string;
    shop_name: string;
    slug: string;
    status: string;
    subscription_status: SubscriptionStatus;
    subscription_ends_at: string | null;
    can_sell: boolean;
};

export type Auth = {
    user: User | null;
    vendor?: AuthVendor | null;
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
