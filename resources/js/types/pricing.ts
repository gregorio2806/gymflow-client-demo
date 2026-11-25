export type OfferingKind = 'subscription' | 'benefit' | 'package';
export interface Offering {
    id: number;
    name: string;
    kind: OfferingKind;
    price_centavos: number;
    duration_amount: number;
    duration_unit: 'days' | 'months';
    branch_ids: number[] | null;
    configuration: {
        discount_unit?: 'percentage' | 'peso';
        discount_input?: string;
        discount_value?: number;
        eligible_plan_ids?: number[];
        applies_to_packages?: boolean;
        applies_to_renewals?: boolean;
        allow_renewal?: boolean;
        deferred_start_allowed?: boolean;
        duration_mode?: 'days' | 'sessions';
        sessions?: number;
        unlimited_validity?: boolean;
        coach_ids?: number[];
    } | null;
}
export interface DiscountRule {
    id: number;
    name: string;
    category: string;
    rule_type: string;
    unit: 'percentage' | 'peso';
    value: number;
    requires_verification: boolean;
    is_active?: boolean;
    product_kinds?: OfferingKind[];
    plan_ids?: number[] | null;
    effective_from?: string | null;
    effective_through?: string | null;
}
export interface PricingSettings {
    require_purchase: boolean;
    packages_require_access: boolean;
    promotion_stacking: 'none' | 'additive';
    tax_profile: 'unconfirmed' | 'non-vat' | 'vat-inclusive';
}
export interface PricingCatalogData {
    offerings: Offering[];
    discounts: DiscountRule[];
    pricingSettings: PricingSettings;
    today: string;
    purchaseBranches: { id: number; name: string }[];
}
export interface CheckoutSummary {
    total_centavos: number;
    lines: {
        plan_id: number;
        kind: OfferingKind;
        name: string;
        original_centavos: number;
        total_centavos: number;
        discounts: {
            reason: string;
            basis_centavos: number;
            savings_centavos: number;
            vat_exemption_centavos?: number;
        }[];
    }[];
}
