<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import {
    Banknote,
    Users,
    Clock,
    UserX,
    CalendarCheck,
    ChartColumn,
    ChartPie,
    Activity,
    TriangleAlert,
    ChevronRight,
    UserPlus,
    RefreshCw,
    CalendarDays,
    ArrowUpRight,
    ArrowDownRight,
} from '@lucide/vue';
import AnimatedCount from '@/components/AnimatedCount.vue';
import DashboardChart from '@/components/DashboardChart.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { index as membersIndex } from '@/routes/members';
import { overview as paymentsIndex } from '@/routes/payments';
import { index as attendanceIndex } from '@/routes/attendance';
import { formatDisplayDate } from '@/lib/date-format';
import type { DashboardAnalytics } from '@/types/dashboard';

const props = defineProps<{
    membershipCounts: {
        active: number;
        expiringThisWeek: number;
        expired: number;
    } | null;
    analytics: DashboardAnalytics;
}>();
defineOptions({
    layout: { breadcrumbs: [{ title: 'Dashboard', href: dashboard() }] },
});
const period = ref(props.analytics.period.value);
watch(
    () => props.analytics.period.value,
    (value) => {
        period.value = value;
    },
);
const months = ref('6');
const days = ref('7');
const busy = ref(false);
function changePeriod(event: Event) {
    period.value = (event.target as HTMLSelectElement).value;
    router.get(
        dashboard().url,
        { period: period.value },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                busy.value = true;
            },
            onFinish: () => {
                busy.value = false;
            },
        },
    );
}
const currency = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    maximumFractionDigits: 2,
});
const money = (cents: number) => currency.format(cents / 100);
const statusColor: Record<string, string> = {
    active: 'text-emerald-500',
    'expiring-soon': 'text-amber-500',
    expired: 'text-red-500',
    suspended: 'text-slate-400',
    scheduled: 'text-blue-500',
    cancelled: 'text-slate-500',
    none: 'text-slate-300',
    other: 'text-violet-500',
};
const segments = computed(() => {
    let offset = 0;
    return (props.analytics.membership?.segments ?? [])
        .filter((segment) => segment.count > 0)
        .map((segment) => {
            const percent =
                (segment.count /
                    Math.max(1, props.analytics.membership?.total ?? 0)) *
                100;
            const item = { ...segment, percent, offset };
            offset += percent;
            return item;
        });
});
const cards = computed(() => [
    ...(props.analytics.revenue
        ? [
              {
                  label: 'Total revenue',
                  value: props.analytics.revenue.totalCentavos / 100,
                  money: true,
                  icon: Banknote,
                  color: 'text-primary',
                  tint: 'bg-primary/5',
                  iconTint: 'bg-primary/10',
                  change: props.analytics.revenue.changePercent,
                  note: 'vs prior equal-length period',
                  href: paymentsIndex().url,
              },
          ]
        : []),
    ...(props.membershipCounts
        ? [
              {
                  label: 'Active memberships',
                  value: props.membershipCounts.active,
                  money: false,
                  icon: Users,
                  color: 'text-emerald-600 dark:text-emerald-400',
                  tint: 'bg-emerald-500/5',
                  iconTint: 'bg-emerald-500/10',
                  change: null,
                  note: 'Includes expiring',
                  href: membersIndex().url,
              },
              {
                  label: 'Expiring this week',
                  value: props.membershipCounts.expiringThisWeek,
                  money: false,
                  icon: Clock,
                  color: 'text-amber-600 dark:text-amber-400',
                  tint: 'bg-amber-500/5',
                  iconTint: 'bg-amber-500/10',
                  change: null,
                  note: 'Within seven days',
                  href: membersIndex({
                      query: { membership_state: 'expiring-soon' },
                  }).url,
              },
              {
                  label: 'Expired memberships',
                  value: props.membershipCounts.expired,
                  money: false,
                  icon: UserX,
                  color: 'text-red-600 dark:text-red-400',
                  tint: 'bg-red-500/5',
                  iconTint: 'bg-red-500/10',
                  change: null,
                  note: 'No current or upcoming period',
                  href: membersIndex({ query: { membership_state: 'expired' } })
                      .url,
              },
          ]
        : []),
    ...(props.analytics.attendance
        ? [
              {
                  label: 'Check-ins today',
                  value: props.analytics.attendance.today,
                  money: false,
                  icon: CalendarCheck,
                  color: 'text-blue-600 dark:text-blue-400',
                  tint: 'bg-blue-500/5',
                  iconTint: 'bg-blue-500/10',
                  change: props.analytics.attendance.changePercent,
                  note: 'vs yesterday',
                  href: attendanceIndex().url,
              },
          ]
        : []),
]);
const revenuePoints = computed(
    () => props.analytics.revenue?.months.slice(-Number(months.value)) ?? [],
);
const attendancePoints = computed(
    () => props.analytics.attendance?.days.slice(-Number(days.value)) ?? [],
);
const methods = computed(
    () =>
        props.analytics.revenue?.methods.filter((method) => method.value > 0) ??
        [],
);
const attention = computed(() =>
    props.membershipCounts
        ? [
              {
                  count: props.membershipCounts.expiringThisWeek,
                  text: 'expiring memberships',
                  help: 'Renewal due within seven days.',
                  state: 'expiring-soon',
                  icon: Clock,
                  color: 'text-amber-600 dark:text-amber-400',
                  tint: 'bg-amber-500/5',
              },
              {
                  count: props.membershipCounts.expired,
                  text: 'expired memberships',
                  help: 'Review renewal options.',
                  state: 'expired',
                  icon: UserX,
                  color: 'text-red-600 dark:text-red-400',
                  tint: 'bg-red-500/5',
              },
              {
                  count:
                      props.analytics.membership?.segments.find(
                          (segment) => segment.key === 'suspended',
                      )?.count ?? 0,
                  text: 'suspended memberships',
                  help: 'Review whether they can resume.',
                  state: 'suspended',
                  icon: Users,
                  color: 'text-blue-600 dark:text-blue-400',
                  tint: 'bg-blue-500/5',
              },
          ].filter((item) => item.count > 0)
        : [],
);
</script>

<template>
    <Head title="Dashboard" />
    <div class="dashboard-page @container space-y-3 p-3 sm:p-4">
        <header
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1 class="text-2xl font-semibold">Dashboard</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Your gym's performance at a glance.
                </p>
            </div>
            <div
                class="reporting-period rounded-xl border bg-card px-3 py-1 focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50 sm:w-56"
            >
                <div class="flex items-center gap-2">
                    <CalendarDays
                        class="size-4 shrink-0"
                        aria-hidden="true"
                    /><NativeSelect
                        :value="period"
                        aria-label="Dashboard reporting period"
                        :disabled="busy"
                        class="border-0 px-0 shadow-none md:h-9!"
                        @change="changePeriod"
                        ><option value="week">This week</option>
                        <option value="month">This month</option>
                        <option value="year">This year</option></NativeSelect
                    >
                </div>
                <p class="text-xs text-muted-foreground">
                    {{ formatDisplayDate(analytics.period.from) }} –
                    {{ formatDisplayDate(analytics.period.through) }}
                </p>
            </div>
        </header>
        <div
            v-if="cards.length"
            class="grid grid-cols-2 gap-2 @min-[60rem]:grid-cols-5"
        >
            <Link
                v-for="card in cards"
                :key="card.label"
                :href="card.href"
                class="flex items-start gap-2 rounded-xl border border-transparent p-3 transition-colors hover:border-border focus-visible:outline-2 focus-visible:outline-ring"
                :class="[
                    card.tint,
                    { 'col-span-2 @min-[32rem]:col-span-1': card.money },
                ]"
            >
                <span
                    class="flex size-8 shrink-0 items-center justify-center rounded-lg"
                    :class="[card.iconTint, card.color]"
                    ><component
                        :is="card.icon"
                        class="size-4"
                        aria-hidden="true"
                /></span>
                <div class="min-w-0">
                    <h2 class="text-xs font-medium">{{ card.label }}</h2>
                    <p
                        class="mt-1 leading-tight font-semibold tabular-nums"
                        :class="card.money ? 'text-lg' : 'text-2xl'"
                    >
                        <template v-if="card.money">{{
                            money(Math.round(card.value * 100))
                        }}</template
                        ><AnimatedCount v-else :value="card.value" />
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        <span
                            v-if="card.change !== null"
                            class="mr-1 inline-flex items-center font-medium"
                            :class="
                                card.change >= 0
                                    ? 'text-emerald-700 dark:text-emerald-400'
                                    : 'text-red-700 dark:text-red-400'
                            "
                            ><component
                                :is="
                                    card.change >= 0
                                        ? ArrowUpRight
                                        : ArrowDownRight
                                "
                                class="size-3"
                                aria-hidden="true"
                            />{{ Math.abs(card.change) }}%</span
                        >{{ card.note }}
                    </p>
                </div>
            </Link>
        </div>
        <div
            v-if="analytics.revenue || analytics.membership"
            class="grid gap-3 @min-[60rem]:grid-cols-5"
        >
            <section
                v-if="analytics.revenue"
                class="min-w-0 rounded-xl border bg-card p-3 @min-[60rem]:col-span-3"
                aria-labelledby="revenue-heading"
            >
                <header
                    class="mb-3 flex flex-wrap items-start justify-between gap-2"
                >
                    <div class="flex gap-2">
                        <ChartColumn
                            class="mt-1 size-5 shrink-0"
                            aria-hidden="true"
                        />
                        <div>
                            <h2
                                id="revenue-heading"
                                class="text-base font-semibold"
                            >
                                Revenue overview
                            </h2>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Recorded payments, excluding voids.
                            </p>
                        </div>
                    </div>
                    <NativeSelect
                        :value="months"
                        @change="
                            months = ($event.target as HTMLSelectElement).value
                        "
                        aria-label="Revenue chart range"
                        class="w-auto md:h-9!"
                        style="width: auto"
                        ><option value="3">Last 3 months</option>
                        <option value="6">Last 6 months</option>
                        <option value="12">Last 12 months</option></NativeSelect
                    >
                </header>
                <DashboardChart
                    :points="revenuePoints"
                    title="Monthly recorded revenue"
                    money
                />
            </section>
            <section
                v-if="analytics.membership"
                class="@container min-w-0 rounded-xl border bg-card p-3 @min-[60rem]:col-span-2"
                aria-labelledby="membership-heading"
            >
                <header class="mb-3 flex gap-2">
                    <Users class="mt-1 size-5 shrink-0" aria-hidden="true" />
                    <div>
                        <h2
                            id="membership-heading"
                            class="text-base font-semibold"
                        >
                            Membership overview
                        </h2>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Current status, one count per member.
                        </p>
                    </div>
                </header>
                <div
                    class="flex flex-col items-center gap-3 @min-[22rem]:flex-row"
                >
                    <div class="relative size-32 shrink-0">
                        <svg
                            viewBox="0 0 120 120"
                            class="size-full -rotate-90"
                            aria-hidden="true"
                        >
                            <circle
                                cx="60"
                                cy="60"
                                r="46"
                                fill="none"
                                stroke-width="16"
                                class="stroke-muted"
                            />
                            <circle
                                v-for="segment in segments"
                                :key="segment.key"
                                cx="60"
                                cy="60"
                                r="46"
                                pathLength="100"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="16"
                                :stroke-dasharray="`${segment.percent} ${100 - segment.percent}`"
                                :stroke-dashoffset="-segment.offset"
                                :class="statusColor[segment.key]"
                            />
                        </svg>
                        <div
                            class="absolute inset-0 flex flex-col items-center justify-center"
                        >
                            <p class="text-2xl font-semibold tabular-nums">
                                <AnimatedCount
                                    :value="analytics.membership.total"
                                />
                            </p>
                            <p
                                class="max-w-16 text-center text-xs leading-tight text-muted-foreground"
                            >
                                Total members
                            </p>
                        </div>
                    </div>
                    <ul class="w-full space-y-2 text-xs">
                        <li
                            v-for="segment in analytics.membership.segments"
                            :key="segment.key"
                            class="flex items-center gap-2"
                        >
                            <span
                                class="size-2.5 shrink-0 rounded-full bg-current"
                                :class="statusColor[segment.key]"
                                aria-hidden="true"
                            /><span class="flex-1">{{ segment.label }}</span
                            ><span class="font-semibold tabular-nums">{{
                                segment.count.toLocaleString('en-PH')
                            }}</span
                            ><span class="w-10 text-right text-muted-foreground"
                                >{{
                                    analytics.membership.total
                                        ? Math.round(
                                              (segment.count /
                                                  analytics.membership.total) *
                                                  100,
                                          )
                                        : 0
                                }}%</span
                            >
                        </li>
                    </ul>
                </div>
            </section>
        </div>
        <div v-if="cards.length" class="grid gap-3 @min-[60rem]:grid-cols-3">
            <section
                v-if="analytics.revenue"
                class="min-w-0 rounded-xl border bg-card p-3"
                aria-labelledby="breakdown-heading"
            >
                <header class="mb-3 flex gap-2">
                    <ChartPie class="mt-1 size-5 shrink-0" aria-hidden="true" />
                    <div>
                        <h2
                            id="breakdown-heading"
                            class="text-base font-semibold"
                        >
                            Revenue breakdown
                        </h2>
                        <p class="mt-1 text-xs text-muted-foreground">
                            By payment method for the selected period.
                        </p>
                    </div>
                </header>
                <p
                    v-if="!methods.length"
                    class="py-8 text-center text-sm text-muted-foreground"
                >
                    No recorded revenue in this period.
                </p>
                <ul v-else class="space-y-3">
                    <li
                        v-for="method in methods"
                        :key="method.label"
                        class="space-y-2"
                    >
                        <div
                            class="flex items-center justify-between gap-2 text-sm"
                        >
                            <span>{{ method.label }}</span
                            ><span class="font-semibold tabular-nums">{{
                                money(method.value)
                            }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <div
                                class="h-2 flex-1 overflow-hidden rounded-full bg-muted"
                            >
                                <div
                                    class="h-full rounded-full bg-primary"
                                    :style="{
                                        width: `${(method.value / Math.max(1, analytics.revenue.totalCentavos)) * 100}%`,
                                    }"
                                />
                            </div>
                            <span
                                class="w-9 text-right text-xs text-muted-foreground"
                                >{{
                                    Math.round(
                                        (method.value /
                                            Math.max(
                                                1,
                                                analytics.revenue.totalCentavos,
                                            )) *
                                            100,
                                    )
                                }}%</span
                            >
                        </div>
                    </li>
                </ul>
            </section>
            <section
                v-if="analytics.attendance"
                class="min-w-0 rounded-xl border bg-card p-3"
                aria-labelledby="attendance-heading"
            >
                <header
                    class="mb-3 flex flex-wrap items-start justify-between gap-2"
                >
                    <div class="flex gap-2">
                        <Activity
                            class="mt-1 size-5 shrink-0"
                            aria-hidden="true"
                        />
                        <div>
                            <h2
                                id="attendance-heading"
                                class="text-base font-semibold"
                            >
                                Attendance trend
                            </h2>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Daily check-ins.
                            </p>
                        </div>
                    </div>
                    <NativeSelect
                        :value="days"
                        @change="
                            days = ($event.target as HTMLSelectElement).value
                        "
                        aria-label="Attendance chart range"
                        class="w-auto md:h-9!"
                        style="width: auto"
                        ><option value="7">Last 7 days</option>
                        <option value="14">Last 14 days</option>
                        <option value="30">Last 30 days</option></NativeSelect
                    >
                </header>
                <DashboardChart
                    :points="attendancePoints"
                    title="Daily gym check-ins"
                    bars
                />
            </section>
            <section
                class="min-w-0 rounded-xl border bg-card p-3"
                aria-labelledby="stats-heading"
            >
                <header class="mb-3 flex gap-2">
                    <ChartPie class="mt-1 size-5 shrink-0" aria-hidden="true" />
                    <div>
                        <h2 id="stats-heading" class="text-base font-semibold">
                            Quick stats
                        </h2>
                        <p class="mt-1 text-xs text-muted-foreground">
                            For the selected reporting period.
                        </p>
                    </div>
                </header>
                <dl class="space-y-2 text-sm">
                    <div
                        v-if="analytics.memberStats"
                        class="flex items-center gap-2"
                    >
                        <UserPlus
                            class="size-4 text-emerald-600"
                            aria-hidden="true"
                        />
                        <dt class="flex-1">New members</dt>
                        <dd class="font-semibold">
                            {{ analytics.memberStats.newMembers }}
                        </dd>
                    </div>
                    <div
                        v-if="analytics.memberStats"
                        class="flex items-center gap-2"
                    >
                        <RefreshCw
                            class="size-4 text-blue-600"
                            aria-hidden="true"
                        />
                        <dt class="flex-1">Renewals</dt>
                        <dd class="font-semibold">
                            {{ analytics.memberStats.renewals }}
                        </dd>
                    </div>
                    <template v-if="analytics.attendance"
                        ><div class="flex items-center gap-2">
                            <CalendarCheck
                                class="size-4 text-primary"
                                aria-hidden="true"
                            />
                            <dt class="flex-1">Total check-ins</dt>
                            <dd class="font-semibold">
                                {{ analytics.attendance.periodTotal }}
                            </dd>
                        </div>
                        <div class="flex items-center gap-2">
                            <Activity
                                class="size-4 text-violet-600"
                                aria-hidden="true"
                            />
                            <dt class="flex-1">Check-ins per day</dt>
                            <dd class="font-semibold">
                                {{ analytics.attendance.dailyAverage }}
                            </dd>
                        </div>
                        <div class="flex items-center gap-2">
                            <CalendarDays
                                class="size-4 text-amber-600"
                                aria-hidden="true"
                            />
                            <dt class="flex-1">Busiest date</dt>
                            <dd class="text-right font-semibold">
                                {{
                                    formatDisplayDate(
                                        analytics.attendance.busiestDay,
                                    )
                                }}
                            </dd>
                        </div></template
                    >
                </dl>
            </section>
        </div>
        <section
            v-if="membershipCounts"
            class="rounded-xl border bg-card p-3"
            aria-labelledby="attention-heading"
        >
            <header class="mb-3 flex items-center justify-between gap-2">
                <div class="flex gap-2">
                    <TriangleAlert
                        class="mt-1 size-5 text-primary"
                        aria-hidden="true"
                    />
                    <div>
                        <h2
                            id="attention-heading"
                            class="text-base font-semibold"
                        >
                            Needs attention
                        </h2>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Memberships that may need a follow-up.
                        </p>
                    </div>
                </div>
                <Button variant="outline" as-child
                    ><Link :href="membersIndex()">View members</Link></Button
                >
            </header>
            <p
                v-if="!attention.length"
                class="py-4 text-sm text-muted-foreground"
            >
                No expiring, expired or suspended memberships to review.
            </p>
            <div v-else class="grid gap-3 @min-[48rem]:grid-cols-3">
                <Link
                    v-for="item in attention"
                    :key="item.state"
                    :href="
                        membersIndex({
                            query: { membership_state: item.state },
                        })
                    "
                    class="flex items-center gap-2 rounded-xl p-3 hover:ring-1 hover:ring-border focus-visible:outline-2 focus-visible:outline-ring"
                    :class="item.tint"
                    ><component
                        :is="item.icon"
                        class="size-6 shrink-0"
                        :class="item.color"
                        aria-hidden="true" />
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium">
                            {{ item.count }} {{ item.text }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ item.help }}
                        </p>
                    </div>
                    <ChevronRight
                        class="size-4 shrink-0"
                        :class="item.color"
                        aria-hidden="true"
                /></Link>
            </div>
        </section>
        <p
            v-if="!cards.length"
            class="rounded-xl border bg-card p-6 text-muted-foreground"
        >
            Use the sidebar to open your available gym tools.
        </p>
        <p v-else class="text-xs text-muted-foreground">
            Membership status is current and independent of profile activation.
            Revenue is recorded cash received, excluding voided payments. All
            dates use your gym's timezone.
        </p>
    </div>
</template>

<style scoped>
.reporting-period :deep(select) {
    border: 0;
    border-radius: 0;
    background: transparent;
    padding-inline: 0;
    box-shadow: none;
}

@media (min-width: 1024px) and (max-width: 1440px) and (max-height: 720px) and (hover: hover) and (pointer: fine) {
    .dashboard-page {
        --dashboard-axis-size: 8px;
    }

    .dashboard-page .text-2xl {
        font-size: 1.333333rem;
        line-height: 1.3;
    }

    .dashboard-page .text-lg {
        font-size: 1rem;
        line-height: 1.35;
    }

    .dashboard-page .text-base {
        font-size: 0.888889rem;
        line-height: 1.4;
    }

    .dashboard-page .text-sm {
        font-size: 0.777778rem;
        line-height: 1.45;
    }

    .dashboard-page .text-xs {
        font-size: 0.666667rem;
        line-height: 1.5;
    }

    .dashboard-page :deep(select) {
        font-size: 0.777778rem;
    }
}
</style>
