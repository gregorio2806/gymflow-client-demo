<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    CalendarDays,
    Download,
    BarChart3,
    Banknote,
    Users,
    CalendarCheck,
    Clock,
} from '@lucide/vue';
import RecordList from '@/components/RecordList.vue';
import RecordMetadata from '@/components/RecordMetadata.vue';
import DashboardChart from '@/components/DashboardChart.vue';
import { formatDisplayDate } from '@/lib/date-format';
import { index } from '@/routes/reports';
import { show as memberProfile } from '@/routes/members';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import NativeSelect from '@/components/NativeSelect.vue';
type Pages<T> = {
    data: T[];
    total?: number;
    from?: number | null;
    to?: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
    current_page?: number;
    last_page?: number;
    links?: { url: string | null; label: string; active: boolean }[];
};
type Filters = {
    period: string;
    month: string;
    from: string;
    through: string;
    branch_id: number | null;
    report: string;
    search: string;
    method: string;
    kind: string;
    payment_state: string;
    source: string;
    status: string;
    per_page: number;
    days_sort: 'asc' | 'desc';
};
const props = defineProps<{
    filters: Filters;
    availableReports: string[];
    detail: {
        columns: { key: string; label: string; format: string | null }[];
        rows: Pages<Record<string, string | number>>;
        metrics: { label: string; value: number | string; money: boolean }[];
        breakdown: { label: string; value: number; money: boolean }[];
        trend: { label: string; value: number }[];
        notes: string[];
        analysis: {
            label: string;
            rows: { label: string; total: number; completed: number }[];
        }[];
    } | null;
    branches: Pages<{ id: number; name: string }>;
    days: Pages<{
        date: string;
        checkIns: number;
        uniqueMembers: number;
        renewals: number;
        revenueCentavos: number;
    }>;
    fullReports: boolean;
}>();
defineOptions({
    layout: { breadcrumbs: [{ title: 'Reports', href: index() }] },
});
const period = ref(props.filters.period),
    month = ref(props.filters.month),
    from = ref(props.filters.from),
    through = ref(props.filters.through),
    branch = ref<number | string>(props.filters.branch_id ?? ''),
    search = ref(props.filters.search),
    method = ref(props.filters.method),
    kind = ref(props.filters.kind),
    paymentState = ref(props.filters.payment_state),
    source = ref(props.filters.source),
    status = ref(props.filters.status),
    perPage = ref(props.filters.per_page),
    daysSort = ref(props.filters.days_sort),
    trendView = ref('daily');
const labels: Record<string, string> = {
    revenue: 'Revenue',
    membership: 'Membership & Payments',
    attendance: 'Attendance',
    appointments: 'Appointments',
};
const pesos = (value: number) =>
    new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(value / 100);
const count = (value: number | null | undefined) =>
    new Intl.NumberFormat('en-PH').format(value ?? 0);
const pageLinks = <T>(pages: Pages<T>) => pages.links?.slice(1, -1) ?? [];
function apply() {
    router.get(
        index().url,
        {
            period: period.value,
            month: period.value === 'month' ? month.value : undefined,
            from: period.value === 'custom' ? from.value : undefined,
            through: period.value === 'custom' ? through.value : undefined,
            branch_id: branch.value || undefined,
            report: props.filters.report,
            search: search.value || undefined,
            method: method.value || undefined,
            kind: kind.value || undefined,
            payment_state: paymentState.value || undefined,
            source: source.value || undefined,
            status: status.value || undefined,
            per_page: perPage.value,
            days_sort: daysSort.value,
        },
        { preserveState: 'errors', preserveScroll: true },
    );
}
function tabHref(report: string) {
    return index({
        query: {
            period: props.filters.period,
            month: props.filters.month,
            from: props.filters.from,
            through: props.filters.through,
            branch_id: props.filters.branch_id || undefined,
            report,
            days_sort: props.filters.days_sort,
        },
    });
}
const csvUrl = computed(
    () =>
        index({ query: { ...props.filters, period: 'custom', export: 'csv' } })
            .url,
);
const chartPoints = computed(() => {
    const points = props.detail?.trend ?? [];
    if (trendView.value === 'daily') return points;
    const groups = new Map<string, { label: string; value: number }>();
    points.forEach((point, i) => {
        const date = new Date(props.filters.from + 'T00:00:00Z');
        date.setUTCDate(date.getUTCDate() + i);
        const key =
            trendView.value === 'monthly'
                ? date.toISOString().slice(0, 7)
                : String(Math.floor(i / 7));
        const label =
            trendView.value === 'monthly'
                ? date.toLocaleDateString('en-PH', {
                      month: 'short',
                      year: 'numeric',
                      timeZone: 'UTC',
                  })
                : point.label;
        const group = groups.get(key) ?? { label, value: 0 };
        group.value += point.value;
        groups.set(key, group);
    });
    return [...groups.values()];
});
const reportColumns = computed(() => {
    const widths: Record<string, string> = {
        member: '18%',
        plan: '20%',
        amount: '13%',
        discount: '10%',
        paid: '10%',
        balance: '10%',
        status: '13%',
    };
    const columns =
        props.detail?.columns.map((column) => ({
            ...column,
            slot: 'cell-' + column.key,
            width:
                props.filters.report === 'membership'
                    ? widths[column.key]
                    : undefined,
        })) ?? [];
    if (props.filters.report === 'membership')
        columns.push({
            key: 'actions',
            label: 'Actions',
            format: null,
            slot: 'cell-actions',
            width: '6%',
        });
    return columns;
});

type ReportRow = Record<
    string,
    string | number | { createdAt: string; createdBy: string }
> & {
    id: string | number;
    audit: { createdAt: string; createdBy: string };
};
const reportRows = computed<ReportRow[]>(
    () =>
        props.detail?.rows.data.map(({ createdAt, createdBy, ...row }) => ({
            ...row,
            id: row.id,
            audit: {
                createdAt: String(createdAt || ''),
                createdBy: String(createdBy || 'Not recorded'),
            },
        })) ?? [],
);
const dailyRows = computed(() =>
    props.days.data.map((row) => ({
        ...row,
        revenue: row.revenueCentavos,
    })),
);
const financial = computed(() => props.filters.report === 'revenue');
const chartTitle = computed(() =>
    props.filters.report === 'attendance'
        ? 'Check-in trend'
        : props.filters.report === 'membership'
          ? 'Purchases created'
          : props.filters.report === 'appointments'
            ? 'Appointment starts'
            : 'Recorded revenue trend',
);
const maxBreakdown = computed(() =>
    Math.max(1, ...(props.detail?.breakdown.map((x) => x.value) ?? [])),
);
</script>
<template>
    <Head title="Reports" />
    <div class="reports-page @container space-y-4 p-4 sm:p-6">
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">Reports</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Analyze detailed records and export your gym's data.
                </p>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-2">
                <Button
                    v-if="fullReports"
                    as-child
                    variant="outline"
                    size="sm"
                    class="border-primary/40 text-primary"
                    ><a :href="csvUrl"
                        ><Download class="size-4" aria-hidden="true" />Export</a
                    ></Button
                >
                <div
                    class="flex items-center gap-2 rounded-lg border bg-card px-3 py-2 text-xs"
                >
                    <CalendarDays class="size-4" aria-hidden="true" />{{
                        formatDisplayDate(filters.from)
                    }}
                    &ndash; {{ formatDisplayDate(filters.through) }}
                </div>
            </div>
        </header>
        <nav
            class="flex gap-1 overflow-x-auto border-b"
            aria-label="Report types"
        >
            <Link
                v-for="tab in availableReports"
                :key="tab"
                :href="tabHref(tab)"
                class="flex min-h-11 shrink-0 items-center border-b-2 px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring"
                :class="
                    filters.report === tab
                        ? 'border-primary font-medium text-primary'
                        : 'border-transparent text-muted-foreground'
                "
                :aria-current="filters.report === tab ? 'page' : undefined"
                >{{ labels[tab] }}</Link
            >
        </nav>
        <form
            id="report-filters"
            class="report-filters flex flex-wrap items-end gap-3 rounded-xl border bg-card p-3"
            @submit.prevent="apply"
        >
            <div class="space-y-1">
                <Label for="report-period">Date range</Label>
                <NativeSelect
                    id="report-period"
                    :value="period"
                    @change="
                        period = ($event.target as HTMLSelectElement).value
                    "
                >
                    <option value="this-year" :disabled="!fullReports">
                        This year{{ fullReports ? '' : ' (Full reports)' }}
                    </option>
                    <option value="this-month">This month</option>
                    <option value="last-month">Last month</option>
                    <option value="month">Specific month</option>
                    <option value="custom">Custom range</option>
                </NativeSelect>
            </div>
            <div v-if="period === 'month'" class="space-y-1">
                <Label for="report-month">Month</Label>
                <Input
                    id="report-month"
                    v-model="month"
                    type="month"
                    min="2000-01"
                    max="2100-12"
                    required
                    class="[color-scheme:light] dark:[color-scheme:dark]"
                />
            </div>
            <div v-if="period === 'custom'" class="space-y-1">
                <Label for="report-from">From</Label
                ><Input
                    id="report-from"
                    v-model="from"
                    type="date"
                    min="2000-01-01"
                    max="2100-12-31"
                    required
                    class="[color-scheme:light] dark:[color-scheme:dark]"
                />
            </div>
            <div v-if="period === 'custom'" class="space-y-1">
                <Label for="report-through">Through</Label
                ><Input
                    id="report-through"
                    v-model="through"
                    type="date"
                    :min="from || '2000-01-01'"
                    max="2100-12-31"
                    required
                    class="[color-scheme:light] dark:[color-scheme:dark]"
                />
            </div>
            <div class="min-w-0 space-y-1">
                <Label for="report-branch">Branch</Label
                ><NativeSelect
                    id="report-branch"
                    :value="branch"
                    @change="
                        branch = ($event.target as HTMLSelectElement).value
                    "
                    ><option value="">All permitted branches</option>
                    <option
                        v-for="item in branches.data"
                        :key="item.id"
                        :value="item.id"
                    >
                        {{ item.name }}
                    </option></NativeSelect
                >
            </div>
            <template v-if="filters.report !== 'revenue'"
                ><div class="space-y-1">
                    <Label for="report-search">{{
                        filters.report === 'appointments'
                            ? 'Member or customer name'
                            : 'Member name or mobile'
                    }}</Label
                    ><Input
                        id="report-search"
                        v-model="search"
                        maxlength="100"
                        placeholder="Search records..."
                    />
                </div>
                <div
                    v-if="
                        financial ||
                        (filters.report === 'membership' &&
                            availableReports.includes('revenue'))
                    "
                    class="space-y-1"
                >
                    <Label for="report-method">Payment method</Label
                    ><NativeSelect
                        id="report-method"
                        :value="method"
                        @change="
                            method = ($event.target as HTMLSelectElement).value
                        "
                        ><option value="">All methods</option>
                        <option value="cash">Cash</option>
                        <option value="gcash">GCash</option>
                        <option value="maya">Maya</option>
                        <option value="bank-transfer">
                            Bank transfer
                        </option></NativeSelect
                    >
                </div>
                <template v-if="filters.report === 'membership'">
                    <div class="space-y-1">
                        <Label for="report-kind">Purchase type</Label
                        ><NativeSelect id="report-kind" v-model="kind"
                            ><option value="">All purchases</option>
                            <option value="subscription">
                                Gym access plans
                            </option>
                            <option value="benefit">Membership plans</option>
                            <option value="package">
                                Training packages
                            </option></NativeSelect
                        >
                    </div>
                    <div
                        v-if="availableReports.includes('revenue')"
                        class="space-y-1"
                    >
                        <Label for="report-payment-state">Payment status</Label
                        ><NativeSelect
                            id="report-payment-state"
                            v-model="paymentState"
                            ><option value="">All payment statuses</option>
                            <option value="paid">Paid</option>
                            <option value="partial">Partial</option>
                            <option value="unpaid">Unpaid</option></NativeSelect
                        >
                    </div>
                </template>
                <div v-if="filters.report === 'appointments'" class="space-y-1">
                    <Label for="report-status">Status</Label
                    ><NativeSelect
                        id="report-status"
                        :value="status"
                        @change="
                            status = ($event.target as HTMLSelectElement).value
                        "
                        ><option value="">All statuses</option>
                        <option
                            v-for="value in filters.report === 'appointments'
                                ? [
                                      'scheduled',
                                      'completed',
                                      'cancelled',
                                      'no-show',
                                  ]
                                : ['recorded', 'voided']"
                            :key="value"
                            :value="value"
                        >
                            {{ value.charAt(0).toUpperCase() + value.slice(1) }}
                        </option></NativeSelect
                    >
                </div>
                <div v-if="filters.report === 'attendance'" class="space-y-1">
                    <Label for="report-source">Source</Label
                    ><NativeSelect
                        id="report-source"
                        :value="source"
                        @change="
                            source = ($event.target as HTMLSelectElement).value
                        "
                        ><option value="">All sources</option>
                        <option value="manual">Manual</option>
                        <option value="qr">QR scan</option></NativeSelect
                    >
                </div></template
            >
            <Button type="submit" class="w-auto shrink-0">Apply filters</Button>
        </form>
        <p class="text-xs text-muted-foreground">
            Manila time.
            {{
                fullReports
                    ? 'Date ranges up to 366 days.'
                    : 'Basic reports: up to 31 days.'
            }}
        </p>
        <nav
            v-if="branches.prev_page_url || branches.next_page_url"
            class="flex gap-2"
            aria-label="Branch options pages"
        >
            <Button v-if="branches.prev_page_url" as-child variant="outline"
                ><Link :href="branches.prev_page_url"
                    >Previous branches</Link
                ></Button
            ><Button v-if="branches.next_page_url" as-child variant="outline"
                ><Link :href="branches.next_page_url"
                    >More branches</Link
                ></Button
            >
        </nav>
        <p
            v-for="(message, key) in $page.props.errors"
            :key="key"
            role="alert"
            class="text-sm text-destructive"
        >
            {{ message }}
        </p>
        <template v-if="detail">
            <dl class="flex flex-wrap items-start gap-3">
                <div
                    v-for="(metric, i) in detail.metrics"
                    :key="metric.label"
                    class="flex min-h-24 w-64 max-w-full flex-none items-start gap-2 rounded-xl border border-transparent p-3"
                    :class="
                        [
                            'bg-primary/5',
                            'bg-emerald-500/5',
                            'bg-blue-500/5',
                            'bg-amber-500/5',
                        ][i % 4]
                    "
                >
                    <span
                        class="flex size-8 shrink-0 items-center justify-center rounded-lg"
                        :class="
                            [
                                'bg-primary/10 text-primary',
                                'bg-emerald-500/10 text-emerald-600',
                                'bg-blue-500/10 text-blue-600',
                                'bg-amber-500/10 text-amber-600',
                            ][i % 4]
                        "
                        aria-hidden="true"
                        ><component
                            :is="
                                financial
                                    ? Banknote
                                    : metric.label.includes('members') ||
                                        metric.label.includes('profiles')
                                      ? Users
                                      : metric.label.includes('hour')
                                        ? Clock
                                        : CalendarCheck
                            "
                            class="size-4"
                    /></span>
                    <div class="min-w-0">
                        <dt class="text-xs font-medium">
                            {{ metric.label }}
                        </dt>
                        <dd
                            class="mt-1 leading-tight font-semibold tabular-nums"
                            :class="metric.money ? 'text-lg' : 'text-2xl'"
                        >
                            {{
                                metric.money
                                    ? pesos(Number(metric.value))
                                    : metric.value.toLocaleString()
                            }}
                        </dd>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{
                                metric.label === 'Previous equal-length period'
                                    ? 'Prior equal-length period'
                                    : 'For selected period'
                            }}
                        </p>
                    </div>
                </div>
            </dl>
            <div
                class="grid items-start gap-3 @min-[48rem]:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]"
            >
                <section
                    class="min-w-0 space-y-3 rounded-xl border bg-card p-3"
                >
                    <header
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <h2
                            class="flex items-center gap-2 text-base font-semibold"
                        >
                            <BarChart3 class="size-4" aria-hidden="true" />{{
                                chartTitle
                            }}
                        </h2>
                        <NativeSelect
                            :value="trendView"
                            style="width: auto"
                            aria-label="Report chart grouping"
                            @change="
                                trendView = ($event.target as HTMLSelectElement)
                                    .value
                            "
                            ><option value="daily">Daily</option>
                            <option value="seven">7-day totals</option>
                            <option value="monthly">
                                Monthly
                            </option></NativeSelect
                        >
                    </header>
                    <DashboardChart
                        :points="chartPoints"
                        :title="chartTitle"
                        :money="financial"
                        :bars="!financial"
                    />
                </section>
                <section class="space-y-3 rounded-xl border bg-card p-3">
                    <h2 class="text-base font-semibold">
                        {{
                            financial
                                ? 'Revenue by method'
                                : filters.report === 'membership'
                                  ? 'Purchase types'
                                  : filters.report === 'attendance'
                                    ? 'Check-ins by source'
                                    : 'Appointment statuses'
                        }}
                    </h2>
                    <p
                        v-if="!detail.breakdown.length"
                        class="text-sm text-muted-foreground"
                    >
                        No breakdown data for this period.
                    </p>
                    <div
                        v-for="item in detail.breakdown"
                        :key="item.label"
                        class="space-y-1"
                    >
                        <div class="flex justify-between gap-3 text-sm">
                            <span>{{ item.label }}</span
                            ><strong class="tabular-nums">{{
                                item.money
                                    ? pesos(item.value)
                                    : item.value.toLocaleString()
                            }}</strong>
                        </div>
                        <div
                            class="h-1.5 rounded-full bg-muted"
                            aria-hidden="true"
                        >
                            <div
                                class="h-1.5 rounded-full bg-primary"
                                :style="{
                                    width:
                                        (item.value / maxBreakdown) * 100 + '%',
                                }"
                            />
                        </div>
                    </div>
                </section>
            </div>
            <div
                v-if="detail.analysis?.length"
                class="grid items-start gap-3 sm:grid-cols-3"
            >
                <section
                    v-for="group in detail.analysis"
                    :key="group.label"
                    class="space-y-3 rounded-xl border bg-card p-3"
                >
                    <h2 class="text-base font-semibold">{{ group.label }}</h2>
                    <p
                        v-if="!group.rows.length"
                        class="text-sm text-muted-foreground"
                    >
                        No appointments in this period.
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Top 10 by bookings - completed / total
                    </p>
                    <div
                        v-for="row in group.rows"
                        :key="row.label"
                        class="flex items-center justify-between gap-2 text-sm"
                    >
                        <span>{{ row.label }}</span
                        ><span class="shrink-0 font-medium"
                            >{{ row.completed }} / {{ row.total }}</span
                        >
                    </div>
                </section>
            </div>
        </template>
        <header
            v-if="detail && filters.report !== 'revenue'"
            class="flex flex-wrap items-center justify-between gap-3"
        >
            <h2 class="text-base font-semibold">
                {{ labels[filters.report] }} records
            </h2>
        </header>
        <template v-if="detail && filters.report !== 'revenue'">
            <section class="space-y-3 rounded-xl border bg-card p-3">
                <RecordList
                    :rows="reportRows"
                    :columns="reportColumns"
                    :wide="
                        filters.report !== 'membership' &&
                        detail.columns.length > 6
                    "
                    :caption="labels[filters.report] + ' detailed records'"
                    row-key="id"
                    ><template
                        v-for="column in reportColumns"
                        :key="column.key"
                        #[column.slot]="{ row }"
                    >
                        <template v-if="column.key === 'actions'"
                            ><Button variant="ghost" size="sm" as-child
                                ><Link
                                    :href="memberProfile(Number(row.memberId))"
                                    >View</Link
                                ></Button
                            ></template
                        >
                        <template v-else>
                            {{
                                column.format === 'money'
                                    ? pesos(Number(row[column.key]))
                                    : column.format === 'date'
                                      ? formatDisplayDate(
                                            String(row[column.key]),
                                        )
                                      : row[column.key]
                            }}
                            <p
                                v-if="
                                    filters.report === 'membership' &&
                                    column.key === 'plan'
                                "
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                {{ row.kindLabel }}
                            </p>
                            <p
                                v-if="
                                    filters.report === 'membership' &&
                                    column.key === 'amount'
                                "
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                Before: {{ pesos(Number(row.original)) }}
                            </p>
                        </template>
                        <details
                            v-if="column.key === detail.columns[0].key"
                            class="mt-1 text-xs text-muted-foreground"
                        >
                            <summary class="cursor-pointer">
                                Record details
                            </summary>
                            <RecordMetadata v-bind="row.audit" />
                            <dl
                                v-if="filters.report === 'membership'"
                                class="mt-2 space-y-1 break-words"
                            >
                                <div>
                                    <dt class="mr-1 inline">Branch:</dt>
                                    <dd class="inline">{{ row.branch }}</dd>
                                </div>
                                <div>
                                    <dt class="mr-1 inline">Start:</dt>
                                    <dd class="inline">{{ row.starts }}</dd>
                                </div>
                                <div>
                                    <dt class="mr-1 inline">Valid through:</dt>
                                    <dd class="inline">{{ row.through }}</dd>
                                </div>
                                <div v-if="row.sessions">
                                    <dt class="mr-1 inline">Sessions:</dt>
                                    <dd class="inline">{{ row.sessions }}</dd>
                                </div>
                                <div v-if="row.discountDetails">
                                    <dt class="mr-1 inline">Savings:</dt>
                                    <dd class="inline">
                                        {{ row.discountDetails }}
                                    </dd>
                                </div>
                            </dl>
                        </details></template
                    ></RecordList
                >
                <footer
                    class="flex flex-wrap items-center justify-between gap-3"
                >
                    <p class="text-xs text-muted-foreground">
                        Showing {{ count(detail.rows.from) }} to
                        {{ count(detail.rows.to) }} of
                        {{ count(detail.rows.total) }} entries
                    </p>
                    <div class="flex flex-wrap items-center gap-2">
                        <Button
                            v-if="detail.rows.prev_page_url"
                            as-child
                            variant="outline"
                            size="sm"
                            ><Link
                                :href="detail.rows.prev_page_url"
                                preserve-scroll
                                >Previous</Link
                            ></Button
                        ><Button v-else variant="outline" size="sm" disabled
                            >Previous</Button
                        ><template
                            v-for="(link, index) in pageLinks(detail.rows)"
                            :key="index"
                            ><Button
                                v-if="/^[0-9]+$/.test(link.label) && link.url"
                                :variant="link.active ? 'default' : 'outline'"
                                :disabled="link.active"
                                size="sm"
                                as-child
                                ><Link :href="link.url" preserve-scroll>{{
                                    link.label
                                }}</Link></Button
                            ><span
                                v-else
                                class="px-2 text-sm text-transparent before:text-foreground before:content-['...']"
                                >…</span
                            ></template
                        ><Button
                            v-if="detail.rows.next_page_url"
                            as-child
                            variant="outline"
                            size="sm"
                            ><Link
                                :href="detail.rows.next_page_url"
                                preserve-scroll
                                >Next</Link
                            ></Button
                        ><Button v-else variant="outline" size="sm" disabled
                            >Next</Button
                        ><NativeSelect
                            :value="perPage"
                            style="width: auto"
                            aria-label="Report records per page"
                            @change="
                                perPage = Number(
                                    ($event.target as HTMLSelectElement).value,
                                );
                                apply();
                            "
                            ><option
                                v-for="size in [10, 15, 25, 50]"
                                :key="size"
                                :value="size"
                            >
                                {{ size }} per page
                            </option></NativeSelect
                        >
                    </div>
                </footer>
            </section>
            <details
                class="rounded-lg border p-3 text-xs text-muted-foreground"
            >
                <summary class="cursor-pointer">
                    How this report is calculated
                </summary>
                <p v-for="note in detail.notes" :key="note" class="mt-2">
                    {{ note }}
                </p>
            </details>
        </template>
        <section
            v-if="filters.report === 'revenue'"
            class="space-y-4 rounded-xl border bg-card p-4"
        >
            <h2 class="text-lg font-semibold">Revenue by day</h2>
            <p class="text-sm text-muted-foreground">
                This table shows cash actually received for each day in the
                selected date range and branch. It excludes voided payments.
                Every row below is also included in the spreadsheet download.
            </p>
            <div
                class="flex flex-wrap items-center justify-between gap-3 text-sm"
            >
                <p class="text-muted-foreground">
                    Showing {{ count(days.from) }} to {{ count(days.to) }} of
                    {{ count(days.total) }} entries
                </p>
                <label class="flex items-center gap-2">
                    <span class="text-muted-foreground">Sort date</span>
                    <NativeSelect
                        v-model="daysSort"
                        aria-label="Sort revenue days"
                        style="width: auto"
                        @change="apply"
                        ><option value="desc">Newest first</option>
                        <option value="asc">Oldest first</option></NativeSelect
                    >
                </label>
            </div>
            <RecordList
                :rows="dailyRows"
                :columns="[
                    { key: 'date', label: 'Date (Manila)' },
                    { key: 'checkIns', label: 'Check-ins' },
                    { key: 'uniqueMembers', label: 'Unique members per day' },
                    { key: 'renewals', label: 'Renewals created' },
                    { key: 'revenue', label: 'Revenue (PHP)' },
                ]"
                caption="Daily summary CSV preview"
                row-key="date"
                empty="No days in this date range."
                wide
            >
                <template #cell-date="{ row }">
                    {{ formatDisplayDate(row.date) }}
                </template>
                <template #cell-revenue="{ row }">
                    {{ pesos(row.revenue) }}
                </template>
            </RecordList>
            <nav
                class="flex flex-wrap items-center justify-end gap-2"
                aria-label="Revenue day pages"
            >
                <div class="flex flex-wrap gap-1">
                    <Button
                        v-if="days.prev_page_url"
                        variant="outline"
                        as-child
                        size="sm"
                    >
                        <Link :href="days.prev_page_url" preserve-scroll
                            >Previous</Link
                        > </Button
                    ><Button v-else variant="outline" size="sm" disabled
                        >Previous</Button
                    ><template
                        v-for="(link, index) in pageLinks(days)"
                        :key="index"
                        ><Button
                            v-if="/^[0-9]+$/.test(link.label) && link.url"
                            :variant="link.active ? 'default' : 'outline'"
                            :disabled="link.active"
                            size="sm"
                            as-child
                            ><Link :href="link.url" preserve-scroll>{{
                                link.label
                            }}</Link></Button
                        ><span
                            v-else
                            class="px-2 text-sm text-transparent before:text-foreground before:content-['...']"
                            >…</span
                        ></template
                    >
                    <Button
                        v-if="days.next_page_url"
                        variant="outline"
                        as-child
                        size="sm"
                    >
                        <Link :href="days.next_page_url" preserve-scroll
                            >Next</Link
                        > </Button
                    ><Button v-else variant="outline" size="sm" disabled
                        >Next</Button
                    >
                </div>
            </nav>
        </section>
    </div>
</template>

<style scoped>
@media (min-width: 1024px) and (max-width: 1440px) and (max-height: 720px) and (hover: hover) and (pointer: fine) {
    .reports-page .text-2xl {
        font-size: 1.333333rem;
        line-height: 1.3;
    }
    .reports-page .text-lg {
        font-size: 1rem;
        line-height: 1.35;
    }
    .reports-page .text-base {
        font-size: 0.888889rem;
        line-height: 1.4;
    }
    .reports-page .text-sm {
        font-size: 0.777778rem;
        line-height: 1.45;
    }
    .reports-page .text-xs {
        font-size: 0.666667rem;
        line-height: 1.5;
    }
    .reports-page :deep(select) {
        font-size: 0.777778rem;
    }
}
.report-filters :deep(input),
.report-filters :deep(select) {
    width: 100%;
}
.report-filters > div,
.report-filters > template > div {
    flex: 0 1 13rem;
    min-width: 0;
}
@media (max-width: 479px) {
    .report-filters > div {
        flex-basis: 100%;
    }
}
</style>
