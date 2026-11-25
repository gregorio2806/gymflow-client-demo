export type MembershipPlanRow = {
    id: number;
    name: string;
    duration_amount: number;
    duration_unit: 'days' | 'months';
    price_centavos: number;
    inclusions: string | null;
    is_active: boolean;
};
