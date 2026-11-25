export type ChartPoint = { label: string; value: number };
export type DashboardAnalytics = {
    period: { value: string; from: string; through: string };
    revenue: {
        totalCentavos: number;
        changePercent: number | null;
        months: ChartPoint[];
        methods: ChartPoint[];
    } | null;
    attendance: {
        today: number;
        changePercent: number | null;
        days: ChartPoint[];
        periodTotal: number;
        dailyAverage: number;
        busiestDay: string | null;
    } | null;
    membership: {
        total: number;
        segments: { key: string; label: string; count: number }[];
    } | null;
    memberStats: { newMembers: number; renewals: number } | null;
};
