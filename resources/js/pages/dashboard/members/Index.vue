<script setup lang="ts">
import RecordList from '@/components/RecordList.vue';
import { formatDisplayDate } from '@/lib/date-format';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Plus,
    Search,
    Users,
    CircleCheck,
    Clock,
    Ban,
    Building2,
    Phone,
} from '@lucide/vue';
import { onBeforeUnmount, ref, watch } from 'vue';
import MemberController from '@/actions/App/Http/Controllers/Dashboard/MemberController';
import AnimatedCount from '@/components/AnimatedCount.vue';
import MemberActions from '@/components/MemberActions.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { MemberRow } from '@/types/members';
const props = defineProps<{
    members: {
        data: MemberRow[];
        total: number;
        from: number | null;
        to: number | null;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: {
        search: string;
        status: string;
        plan_id: number | null;
        membership_state: string;
        per_page: number;
    };
    statistics: {
        total: number;
        active: number;
        expiring: number;
        inactive: number;
    };
    planOptions: { id: number; name: string }[];
    canCreate: boolean;
    paymentSelection: boolean;
}>();
defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Members', href: MemberController.index() }],
    },
});
const search = ref(props.filters.search);
const status = ref(props.filters.status);
const plan = ref<number | string>(props.filters.plan_id ?? '');
const membership = ref(props.filters.membership_state);
const perPage = ref(props.filters.per_page);
let timer: ReturnType<typeof setTimeout> | undefined;
let cancelSearch: (() => void) | undefined;
let syncing = false;
function filter() {
    router.get(
        MemberController.index(),
        {
            search: search.value,
            status: status.value,
            plan_id: plan.value,
            membership_state: membership.value,
            per_page: perPage.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onCancelToken: (token) => {
                cancelSearch = () => token.cancel();
            },
        },
    );
}
watch(
    search,
    () => {
        if (syncing) return;
        cancelSearch?.();
        clearTimeout(timer);
        timer = setTimeout(filter, 300);
    },
    { flush: 'sync' },
);
watch(
    [status, plan, membership, perPage],
    () => {
        if (syncing) return;
        cancelSearch?.();
        clearTimeout(timer);
        filter();
    },
    { flush: 'sync' },
);
watch(
    () => props.filters,
    (value) => {
        syncing = true;
        search.value = value.search;
        status.value = value.status;
        plan.value = value.plan_id ?? '';
        membership.value = value.membership_state;
        perPage.value = value.per_page;
        syncing = false;
    },
);
onBeforeUnmount(() => {
    clearTimeout(timer);
    cancelSearch?.();
});
const initials = (name: string) =>
    name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((word) => word[0])
        .join('')
        .toUpperCase();
const stateClass = (state: string) =>
    state === 'Active'
        ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400'
        : state === 'Expiring soon'
          ? 'bg-amber-500/15 text-amber-700 dark:text-amber-300'
          : state === 'Expired'
            ? 'bg-primary/10 text-primary'
            : 'bg-muted text-muted-foreground';
</script>
<template>
    <Head title="Members" />
    <div class="@container space-y-5 p-4 sm:p-6">
        <header
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <span
                    class="flex size-12 items-center justify-center rounded-xl bg-muted"
                    ><Users class="size-6"
                /></span>
                <div>
                    <h1 class="text-2xl font-semibold">Members</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Manage gym members, view details, and record payments.
                    </p>
                </div>
            </div>
            <Button v-if="canCreate" as-child
                ><Link :href="MemberController.create()"
                    ><Plus class="size-4" />Add member</Link
                ></Button
            >
        </header>
        <dl class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div
                v-for="card in [
                    {
                        label: 'Total members',
                        value: statistics.total,
                        icon: Users,
                    },
                    {
                        label: 'Active memberships',
                        value: statistics.active,
                        icon: CircleCheck,
                    },
                    {
                        label: 'Expiring soon',
                        value: statistics.expiring,
                        icon: Clock,
                    },
                    {
                        label: 'Inactive profiles',
                        value: statistics.inactive,
                        icon: Ban,
                    },
                ]"
                :key="card.label"
                class="flex items-center gap-3 rounded-xl border bg-card p-4"
            >
                <span
                    class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-muted"
                    ><component :is="card.icon" class="size-5"
                /></span>
                <div>
                    <dd class="text-xl font-semibold tabular-nums">
                        <AnimatedCount :value="card.value" />
                    </dd>
                    <dt class="text-sm text-muted-foreground">
                        {{ card.label }}
                    </dt>
                </div>
            </div>
        </dl>
        <p class="text-xs text-muted-foreground">
            Memberships can be used at every gym branch. Active memberships
            include those expiring soon; profile activation is separate.
        </p>
        <div
            class="grid gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(16rem,2fr)_repeat(3,minmax(0,1fr))]"
        >
            <div class="relative">
                <Search
                    aria-hidden="true"
                    class="pointer-events-none absolute top-3 left-3 size-5 text-muted-foreground"
                /><Input
                    v-model="search"
                    aria-label="Search members"
                    type="search"
                    maxlength="100"
                    placeholder="Search name, mobile, or email"
                    class="pl-10"
                />
            </div>
            <NativeSelect
                :value="status"
                @change="status = ($event.target as HTMLSelectElement).value"
                aria-label="Profile activation"
                ><option value="all">All profiles</option>
                <option value="active">Active profiles</option>
                <option value="inactive">
                    Inactive profiles
                </option></NativeSelect
            >
            <NativeSelect
                :value="plan"
                @change="plan = ($event.target as HTMLSelectElement).value"
                aria-label="Membership plan"
                ><option value="">All membership plans</option>
                <option
                    v-for="item in planOptions"
                    :key="item.id"
                    :value="item.id"
                >
                    {{ item.name }}
                </option></NativeSelect
            >
            <NativeSelect
                :value="membership"
                @change="
                    membership = ($event.target as HTMLSelectElement).value
                "
                aria-label="Membership status"
                ><option value="">All membership statuses</option>
                <option value="active">Active</option>
                <option value="expiring-soon">Expiring soon</option>
                <option value="expired">Expired</option>
                <option value="scheduled">Scheduled</option>
                <option value="suspended">Suspended</option>
                <option value="cancelled">Cancelled</option>
                <option value="none">No membership</option></NativeSelect
            >
        </div>
        <div
            v-if="!members.data.length"
            class="rounded-xl border py-12 text-center"
        >
            <Users class="mx-auto mb-3 size-8 text-muted-foreground" />
            <p>No members match your filters.</p>
        </div>
        <RecordList
            :rows="members.data"
            :columns="[
                { key: 'name', label: 'Member', width: '25%' },
                { key: 'number', label: '#', width: '4%' },
                { key: 'mobile', label: 'Contact', width: '13%' },
                { key: 'branch', label: 'Home branch', width: '12%' },
                { key: 'planName', label: 'Membership', width: '14%' },
                { key: 'membershipStatus', label: 'Status', width: '11%' },
                { key: 'renewalDue', label: 'Renewal due', width: '10%' },
                { key: 'actions', label: 'Actions', width: '11%' },
            ]"
            caption="Members with membership details"
            v-else
            row-key="id"
            wide
            ><template #cell-number="{ index }">{{
                (members.from ?? 1) + index
            }}</template>
            <template #cell-name="{ row: member }"
                ><div class="flex items-center gap-3">
                    <span
                        class="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/10 font-semibold text-primary"
                        aria-hidden="true"
                        >{{ initials(member.name) }}</span
                    >
                    <div class="min-w-0">
                        <p class="font-medium break-words">{{ member.name }}</p>
                        <p
                            v-if="member.email"
                            class="mt-1 text-xs break-all text-muted-foreground"
                        >
                            {{ member.email }}
                        </p>
                    </div>
                </div></template
            >
            <template #cell-mobile="{ row: member }"
                ><span
                    v-if="member.mobile"
                    class="flex flex-wrap items-center gap-1"
                    ><Phone class="size-3 shrink-0" />{{ member.mobile }}</span
                ><span v-else class="text-muted-foreground"
                    >No mobile number</span
                ></template
            >
            <template #cell-branch="{ row: member }"
                ><span
                    class="inline-flex max-w-full items-center gap-1 rounded-full bg-muted px-2 py-1 text-xs"
                    ><Building2 class="size-3 shrink-0" />{{
                        member.branch
                    }}</span
                ></template
            >
            <template #cell-planName="{ row: member }">{{
                member.planName || 'No plan'
            }}</template>
            <template #cell-membershipStatus="{ row: member }"
                ><Badge
                    :class="stateClass(member.membershipStatus)"
                    class="whitespace-normal"
                    >{{ member.membershipStatus }}</Badge
                >
                <p
                    v-if="!member.is_active"
                    class="mt-1 text-xs text-muted-foreground"
                >
                    Inactive profile
                </p></template
            >
            <template #cell-renewalDue="{ row: member }">{{
                formatDisplayDate(member.renewalDue)
            }}</template>
            <template #cell-actions="{ row: member }"
                ><MemberActions :member="member" /></template
        ></RecordList>
        <nav
            class="flex flex-wrap items-center justify-between gap-3"
            aria-label="Member pagination"
        >
            <p class="text-sm text-muted-foreground">
                Showing {{ members.from ?? 0 }}–{{ members.to ?? 0 }} of
                {{ members.total }} members
            </p>
            <div class="flex flex-wrap items-center gap-2">
                <Button
                    variant="outline"
                    :disabled="!members.prev_page_url"
                    :as-child="!!members.prev_page_url"
                    ><Link
                        v-if="members.prev_page_url"
                        :href="members.prev_page_url"
                        preserve-scroll
                        >Previous</Link
                    ><span v-else>Previous</span></Button
                ><Button
                    variant="outline"
                    :disabled="!members.next_page_url"
                    :as-child="!!members.next_page_url"
                    ><Link
                        v-if="members.next_page_url"
                        :href="members.next_page_url"
                        preserve-scroll
                        >Next</Link
                    ><span v-else>Next</span></Button
                ><NativeSelect
                    :value="perPage"
                    @change="
                        perPage = Number(
                            ($event.target as HTMLSelectElement).value,
                        )
                    "
                    aria-label="Members per page"
                    class="w-32"
                    ><option :value="8">8 per page</option>
                    <option :value="25">25 per page</option>
                    <option :value="50">50 per page</option></NativeSelect
                >
            </div>
        </nav>
    </div>
</template>
