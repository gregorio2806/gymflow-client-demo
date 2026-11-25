export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type GymRole = 'owner' | 'staff' | 'coach' | 'branch-manager';

export type CurrentGym = {
    id: number;
    name: string;
    slug: string;
    role: GymRole | null;
    plan: 'starter' | 'pro' | 'multi_branch';
    hasExtraBranches: boolean;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
