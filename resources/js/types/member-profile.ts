export type PaymentFormData = {
    member: { id: number; name: string };
    canRecord: boolean;
    subscriptions: {
        data: {
            id: number;
            planName: string;
            startsOn: string;
            balanceCentavos: number;
            duration: string;
            canRecord: boolean;
        }[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    purchases: {
        data: {
            id: number;
            kind: string;
            planName: string;
            startsOn: string;
            balanceCentavos: number;
            duration: string;
            canRecord: boolean;
        }[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
};
export type PaymentHistoryData = {
    member: { id: number; name: string };
    payments: {
        data: {
            id: number;
            createdAt: string;
            createdBy: string;
            amountCentavos: number;
            planName: string;
            discountCentavos: number;
            discountPercent: number;
            discountScope: string | null;
            discountBaseCentavos: number | null;
            method: string;
            reference: string | null;
            status: string;
            paidAt: string;
            branchName: string | null;
            voidReason: string | null;
            voidedAt: string | null;
            voidedBy: string | null;
            canVoid: boolean;
        }[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
};
export type MemberProfile = {
    id: number;
    name: string;
    is_active: boolean;
    mobile: string;
    email: string | null;
    branchName: string | null;
    hasConsent: boolean;
    membershipStatus: string;
    joinedOn: string;
    planName: string | null;
    planAmountCentavos: number | null;
    planDuration: string | null;
    startsOn: string | null;
    validThrough: string | null;
    renewalDue: string | null;
    hasAttendanceQr: boolean;
};
export type MembershipData = {
    member: MemberProfile;
    canManage: boolean;
    canRenew: boolean;
    today: string;
    plans: {
        id: number;
        name: string;
        price_centavos: number;
        duration_amount: number;
        duration_unit: string;
    }[];
    subscriptions: {
        data: {
            id: number;
            createdAt: string;
            createdBy: string;
            plan_name: string;
            price_centavos: number;
            discountPercent: number;
            discountType: string;
            discountMode: string;
            discountCentavos: number;
            originalPriceCentavos: number | null;
            status: string;
            startsOn: string;
            validThrough: string;
            canSuspend: boolean;
            canResume: boolean;
            canCancel: boolean;
        }[];
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    currentSubscriptions: {
        id: number;
        planName: string;
        startsOn: string;
        validThrough: string;
        status: string;
    }[];
    currentMembershipPlans: {
        id: number;
        planName: string;
        priceCentavos: number;
        startsOn: string;
        validThrough: string | null;
        status: string;
    }[];
    membershipPlans: {
        data: {
            id: number;
            planName: string;
            priceCentavos: number;
            startsOn: string;
            validThrough: string | null;
            status: string;
        }[];
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
};
