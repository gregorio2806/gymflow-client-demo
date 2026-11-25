<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Users,
    Clock,
    Star,
    Download,
    CalendarDays,
    Filter,
    Search,
    ChevronLeft,
    ChevronRight,
    X,
} from '@lucide/vue';
import AttendanceController from '@/actions/App/Http/Controllers/Dashboard/AttendanceController';
import RecordList from '@/components/RecordList.vue';
import RecordMetadata from '@/components/RecordMetadata.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatDisplayDate } from '@/lib/date-format';
const props = defineProps<{
    filters: {
        view: string;
        date: string;
        branch_id: number | null;
        search: string;
        source: string;
        per_page: number;
    };
    period: { from: string; through: string };
    summary: { checkIns: number; uniqueMembers: number };
    insights: { dailyAverage: number; peakTime: string | null };
    canExport: boolean;
    defaultBranchId: number | null;
    branches: { id: number; name: string }[];
    attendance: {
        data: {
            id: number;
            createdAt: string;
            createdBy: string;
            memberName: string;
            memberMobile: string;
            memberInitials: string;
            branchName: string;
            checkedInAt: string;
            source: string;
        }[];
        total: number;
        from: number | null;
        to: number | null;
        current_page: number;
        last_page: number;
        links: { url: string | null; label: string; active: boolean }[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}>();
defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Attendance History',
                href: AttendanceController.index(),
            },
        ],
    },
});
const page = usePage();
const view = ref(props.filters.view),
    date = ref(props.filters.date),
    branch = ref(
        props.filters.branch_id ? String(props.filters.branch_id) : '',
    ),
    search = ref(props.filters.search),
    source = ref(props.filters.source),
    perPage = ref(props.filters.per_page);
const busy = ref(false),
    error = ref('');
watch(
    () => props.filters,
    (f) => {
        view.value = f.view;
        date.value = f.date;
        branch.value = f.branch_id ? String(f.branch_id) : '';
        search.value = f.search;
        source.value = f.source;
        perPage.value = f.per_page;
    },
);
function params() {
    return {
        view: view.value,
        date: date.value,
        branch_id: branch.value || undefined,
        search: search.value || undefined,
        source: source.value || undefined,
        per_page: perPage.value,
    };
}
function filter() {
    router.get(AttendanceController.index().url, params(), {
        preserveState: true,
        preserveScroll: true,
        onStart: () => {
            busy.value = true;
            error.value = '';
        },
        onFinish: () => {
            busy.value = false;
        },
        onError: (errors) => {
            error.value =
                Object.values(errors)[0] || 'Could not load attendance.';
        },
    });
}
function clear() {
    search.value = '';
    source.value = '';
    branch.value = page.props.currentBranch
        ? String(page.props.currentBranch.id)
        : '';
    filter();
}
const exportUrl = computed(
    () =>
        AttendanceController.index({ query: { ...params(), export: 'csv' } })
            .url,
);
const rows = computed(() =>
    props.attendance.data.map(({ createdAt, createdBy, ...record }) => ({
        ...record,
        audit: { createdAt, createdBy },
    })),
);
const cards = computed(() => [
    {
        label: 'Total check-ins',
        value: props.summary.checkIns,
        icon: Users,
        tone: 'bg-primary/5',
        iconTone: 'bg-primary/10 text-primary',
    },
    {
        label: 'Unique members',
        value: props.summary.uniqueMembers,
        icon: Users,
        tone: 'bg-violet-500/5',
        iconTone: 'bg-violet-500/10 text-violet-600 dark:text-violet-400',
    },
    {
        label: 'Avg. check-ins per day',
        value: props.insights.dailyAverage,
        icon: Clock,
        tone: 'bg-blue-500/5',
        iconTone: 'bg-blue-500/10 text-blue-600 dark:text-blue-400',
    },
    {
        label: 'Peak time',
        value: props.insights.peakTime || '—',
        icon: Star,
        tone: 'bg-amber-500/5',
        iconTone: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
    },
]);
</script>
<template>
    <Head title="Attendance History" />
    <div class="@container space-y-4 p-4 sm:p-6">
        <header
            class="flex flex-col justify-between gap-3 @min-[48rem]:flex-row @min-[48rem]:items-center"
        >
            <div>
                <h1 class="text-2xl font-semibold">Attendance History</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Review member check-ins across branches.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button
                    v-if="canExport"
                    as-child
                    variant="outline"
                    class="border-primary/40 text-primary"
                    ><a :href="exportUrl"
                        ><Download class="size-4" aria-hidden="true" />
                        Export</a
                    ></Button
                >
                <div
                    class="flex items-center gap-2 rounded-lg border bg-card px-3 py-2 text-xs"
                >
                    <CalendarDays
                        class="size-4 shrink-0"
                        aria-hidden="true"
                    /><span
                        >{{ formatDisplayDate(period.from)
                        }}<template v-if="period.from !== period.through">
                            – {{ formatDisplayDate(period.through) }}</template
                        ></span
                    >
                </div>
            </div>
        </header>
        <dl class="grid grid-cols-2 gap-3 @min-[48rem]:grid-cols-4">
            <div
                v-for="card in cards"
                :key="card.label"
                class="flex items-start gap-3 rounded-xl p-3"
                :class="card.tone"
            >
                <component
                    :is="card.icon"
                    class="size-9 shrink-0 rounded-lg p-2"
                    :class="card.iconTone"
                    aria-hidden="true"
                />
                <div class="min-w-0">
                    <dt class="text-xs text-muted-foreground">
                        {{ card.label }}
                    </dt>
                    <dd
                        class="mt-1 font-semibold tabular-nums"
                        :class="
                            card.label === 'Peak time'
                                ? 'text-base'
                                : 'text-2xl'
                        "
                    >
                        {{
                            typeof card.value === 'number'
                                ? card.value.toLocaleString()
                                : card.value
                        }}
                    </dd>
                    <p
                        v-if="card.label === 'Peak time'"
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        Busiest hour · Manila
                    </p>
                </div>
            </div>
        </dl>
        <section
            class="min-w-0 space-y-3 rounded-xl border bg-card p-3"
            aria-label="Attendance records"
        >
            <h2 class="text-base font-semibold">Attendance history</h2>
            <form
                class="flex flex-wrap items-center gap-2"
                @submit.prevent="filter"
            >
                <div class="min-w-[13rem] flex-[1_1_13rem]">
                    <div class="relative">
                        <Search
                            class="pointer-events-none absolute top-3 left-3 size-4 text-muted-foreground"
                            aria-hidden="true"
                        /><Input
                            v-model="search"
                            class="pl-9"
                            aria-label="Search attendance members"
                            placeholder="Search name, mobile or member ID…"
                            maxlength="100"
                        />
                    </div>
                </div>
                <NativeSelect
                    id="attendance-view"
                    class="w-20 shrink-0"
                    :value="view"
                    aria-label="Attendance period"
                    @change="view = ($event.target as HTMLSelectElement).value"
                    ><option value="day">Daily</option>
                    <option value="week">Weekly</option>
                    <option value="month">Monthly</option></NativeSelect
                >
                <Input
                    id="attendance-date"
                    v-model="date"
                    class="w-28 shrink-0"
                    type="date"
                    aria-label="Date in the period"
                    min="2000-01-01"
                    max="2100-12-31"
                    required
                />
                <NativeSelect
                    id="attendance-history-branch"
                    class="w-28 shrink-0"
                    :value="branch"
                    aria-label="Check-in branch"
                    @change="
                        branch = ($event.target as HTMLSelectElement).value
                    "
                    ><option v-if="!page.props.currentBranch" value="">
                        All branches
                    </option>
                    <option
                        v-for="item in branches"
                        :key="item.id"
                        :value="String(item.id)"
                    >
                        {{ item.name }}
                    </option></NativeSelect
                >
                <NativeSelect
                    class="w-28 shrink-0"
                    :value="source"
                    aria-label="Check-in source"
                    @change="
                        source = ($event.target as HTMLSelectElement).value
                    "
                    ><option value="">All sources</option>
                    <option value="manual">Manual</option>
                    <option value="qr">QR scan</option></NativeSelect
                >
                <Button
                    type="submit"
                    size="sm"
                    class="w-auto shrink-0 bg-primary text-primary-foreground hover:bg-primary/90"
                    :disabled="busy"
                >
                    <Filter class="size-4" />
                    {{ busy ? 'Loading…' : 'Apply filters' }} </Button
                ><Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="w-auto shrink-0 border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100 hover:text-amber-900 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-200 dark:hover:bg-amber-950/50 dark:hover:text-amber-100"
                    :disabled="busy"
                    @click="clear"
                    ><X class="size-4" />Clear filters</Button
                >
            </form>
            <p v-if="error" role="alert" class="text-sm text-destructive">
                {{ error }}
            </p>
            <p
                v-if="!rows.length"
                class="py-8 text-center text-sm text-muted-foreground"
            >
                No check-ins match these filters.
            </p>
            <RecordList
                v-else
                :rows="rows"
                :columns="[
                    { key: 'memberName', label: 'Member' },
                    { key: 'checkedInAt', label: 'Check-in time' },
                    { key: 'branchName', label: 'Branch' },
                    { key: 'source', label: 'Source' },
                    { key: 'status', label: 'Status' },
                ]"
                caption="Attendance records for the selected period (Manila time)"
                row-key="id"
            >
                <template #cell-memberName="{ row: record }"
                    ><div class="flex items-start gap-3">
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-full bg-blue-500/10 text-xs font-semibold text-blue-700 dark:text-blue-300"
                            aria-hidden="true"
                            >{{ record.memberInitials }}</span
                        >
                        <div class="min-w-0">
                            <p class="font-medium">
                                {{ record.memberName }}
                            </p>
                            <p
                                v-if="record.memberMobile"
                                class="text-xs text-muted-foreground"
                            >
                                {{ record.memberMobile }}
                            </p>
                            <details class="mt-1 text-xs text-muted-foreground">
                                <summary
                                    class="cursor-pointer rounded focus-visible:ring-2 focus-visible:ring-ring"
                                >
                                    Record details
                                </summary>
                                <RecordMetadata v-bind="record.audit" />
                            </details>
                        </div></div
                ></template>
                <template #cell-checkedInAt="{ row: record }">{{
                    formatDisplayDate(record.checkedInAt)
                }}</template>
                <template #cell-status
                    ><span
                        class="inline-flex items-center gap-1.5 rounded-md bg-emerald-500/10 px-2 py-1 text-xs font-medium text-emerald-700 dark:text-emerald-400"
                        ><span
                            class="size-1.5 rounded-full bg-emerald-500"
                            aria-hidden="true"
                        />Present</span
                    ></template
                >
            </RecordList>
            <footer
                class="flex flex-wrap items-center justify-between gap-3 text-sm"
            >
                <p class="text-muted-foreground">
                    Showing {{ attendance.from || 0 }} to
                    {{ attendance.to || 0 }} of
                    {{ attendance.total.toLocaleString() }} records
                </p>
                <div class="flex flex-wrap items-center gap-2">
                    <nav
                        class="flex items-center gap-1"
                        aria-label="Attendance history pagination"
                    >
                        <Button
                            v-if="attendance.prev_page_url"
                            as-child
                            variant="outline"
                            size="icon"
                            aria-label="Previous page"
                            ><Link
                                :href="attendance.prev_page_url"
                                preserve-scroll
                                ><ChevronLeft class="size-4" /></Link
                        ></Button>
                        <template
                            v-for="(item, index) in attendance.links.slice(
                                1,
                                -1,
                            )"
                            :key="index"
                            ><Button
                                v-if="item.url"
                                as-child
                                :variant="item.active ? 'default' : 'outline'"
                                size="icon"
                                ><Link
                                    :href="item.url"
                                    :aria-current="
                                        item.active ? 'page' : undefined
                                    "
                                    preserve-scroll
                                    >{{ item.label }}</Link
                                ></Button
                            ><span v-else class="px-1 text-muted-foreground"
                                >…</span
                            ></template
                        >
                        <Button
                            v-if="attendance.next_page_url"
                            as-child
                            variant="outline"
                            size="icon"
                            aria-label="Next page"
                            ><Link
                                :href="attendance.next_page_url"
                                preserve-scroll
                                ><ChevronRight class="size-4" /></Link
                        ></Button>
                    </nav>
                    <NativeSelect
                        :value="perPage"
                        aria-label="Records per page"
                        style="width: auto"
                        @change="
                            perPage = Number(
                                ($event.target as HTMLSelectElement).value,
                            );
                            filter();
                        "
                        ><option
                            v-for="size in [5, 10, 25, 50]"
                            :key="size"
                            :value="size"
                        >
                            {{ size }} per page
                        </option></NativeSelect
                    >
                </div>
            </footer>
        </section>
    </div>
</template>
