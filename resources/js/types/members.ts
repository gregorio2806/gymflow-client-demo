export type MemberRow = {
    id: number;
    name: string;
    mobile: string;
    email: string | null;
    is_active: boolean;
    membershipStatus: string;
    branch: string | null;
    canEdit: boolean;
    planName: string | null;
    renewalDue: string | null;
};
