<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { CalendarDays, Eye, Plus } from '@lucide/vue';
import RecordList from '@/components/RecordList.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetDescription,
} from '@/components/ui/sheet';
import {
    Dialog,
    DialogScrollContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import {
    index,
    store,
    update,
    status,
    slots as availableSlots,
    members as searchMembers,
} from '@/routes/appointments';
import { index as calendar } from '@/routes/coach-calendar';
type Person = {
    id: number;
    name: string;
    packages?: { id: number; name: string; remaining: number }[];
};
type Pages<T> = {
    data: T[];
    total: number;
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
type Booking = {
    id: number;
    member_id: number | null;
    member_purchase_id: number | null;
    packageName: string | null;
    customer_name: string;
    customer: string;
    coach_id: number;
    branch_id: number;
    service: string;
    serviceLabel: string;
    coach: string;
    branch: string;
    date: string;
    time: string;
    starts_at: string;
    ends_at: string;
    location: string | null;
    notes: string | null;
    status: string;
    revision: number;
    canManage: boolean;
    hasEnded: boolean;
    createdAt: string;
    createdBy: string;
};
type Slot = { starts_at: string; ends_at: string; label: string };
const props = defineProps<{
    filters: {
        search: string;
        date: string;
        coach_id: number | string;
        branch_id: number | string;
        service: string;
        status: string;
    };
    appointments: Pages<Booking>;
    selected: Booking | null;
    coaches: Pages<Person>;
    branches: Pages<Person>;
    services: { value: string; label: string }[];
    metrics: {
        today: number;
        upcoming: number;
        completed: number;
        missed: number;
    };
    canManage: boolean;
    today: string;
    requestKey: string;
    timezone: string;
}>();
defineOptions({
    layout: { breadcrumbs: [{ title: 'Appointments', href: index() }] },
});
const filters = ref({ ...props.filters });
watch(
    () => props.filters,
    (value) => {
        filters.value = { ...value };
    },
);
const detail = ref<Booking | null>(props.selected);
const detailOpen = ref(!!props.selected);
watch(
    () => props.selected,
    (value) => {
        if (value) {
            detail.value = value;
            detailOpen.value = true;
        }
    },
);
watch(
    () => props.appointments.data,
    (rows) => {
        if (detail.value)
            detail.value =
                rows.find((row) => row.id === detail.value?.id) ?? detail.value;
    },
);
function applyFilters() {
    router.get(index().url, filters.value, {
        preserveState: true,
        preserveScroll: true,
    });
}
function view(row: Booking) {
    detail.value = row;
    detailOpen.value = true;
}
const editing = ref<Booking | null>(null);
const editorOpen = ref(false);
const identity = ref<'member' | 'customer'>('member');
const memberSearch = ref('');
const memberResults = ref<Person[]>([]);
const selectedMember = ref<Person | null>(null);
const date = ref(props.today);
const duration = ref(60);
const timeSlots = ref<Slot[]>([]);
const loadingSlots = ref(false);
const loadingMembers = ref(false);
const lookupError = ref('');
const form = useForm({
    member_id: '' as string | number,
    member_purchase_id: '' as string | number,
    customer_name: '',
    service: '',
    coach_id: '' as string | number,
    branch_id: '' as string | number,
    starts_at: '',
    ends_at: '',
    location: '',
    notes: '',
    request_key: '',
    revision: 1,
});
const errorSummary = ref<HTMLElement | null>(null);
function focusError() {
    nextTick(() => errorSummary.value?.focus());
}
function openEditor(row?: Booking) {
    editing.value = row ?? null;
    identity.value = row && !row.member_id ? 'customer' : 'member';
    selectedMember.value = row?.member_id
        ? { id: row.member_id, name: row.customer }
        : null;
    memberSearch.value = '';
    memberResults.value = [];
    lookupError.value = '';
    date.value =
        row?.starts_at.slice(0, 10) ?? (filters.value.date || props.today);
    duration.value = row
        ? Math.round(
              (Date.parse(row.ends_at) - Date.parse(row.starts_at)) / 60000,
          )
        : 60;
    form.defaults({
        member_id: row?.member_id ?? '',
        member_purchase_id: row?.member_purchase_id ?? '',
        customer_name: row?.customer_name ?? '',
        service: row?.service ?? '',
        coach_id: row?.coach_id ?? props.coaches.data[0]?.id ?? '',
        branch_id: row?.branch_id ?? props.branches.data[0]?.id ?? '',
        starts_at: row?.starts_at ?? '',
        ends_at: row?.ends_at ?? '',
        location: row?.location ?? '',
        notes: row?.notes ?? '',
        request_key: props.requestKey,
        revision: row?.revision ?? 1,
    });
    form.reset();
    form.clearErrors();
    detailOpen.value = false;
    editorOpen.value = true;
    nextTick(() => {
        loadSlots();
        loadMembers();
    });
}
let slotAbort: AbortController | undefined;
let memberAbort: AbortController | undefined;
let searchTimer: ReturnType<typeof setTimeout> | undefined;
async function loadSlots() {
    slotAbort?.abort();
    timeSlots.value = [];
    if (
        !editorOpen.value ||
        !form.coach_id ||
        !form.branch_id ||
        !date.value ||
        duration.value < 15 ||
        duration.value > 480
    )
        return;
    const abort = new AbortController();
    slotAbort = abort;
    loadingSlots.value = true;
    lookupError.value = '';
    try {
        const response = await fetch(
            availableSlots({
                query: {
                    coach_id: form.coach_id,
                    branch_id: form.branch_id,
                    date: date.value,
                    duration: duration.value,
                    ...(editing.value ? { except: editing.value.id } : {}),
                },
            }).url,
            { signal: abort.signal, headers: { Accept: 'application/json' } },
        );
        if (!response.ok)
            throw new Error(
                'Could not load available times. Check the coach and branch, then try again.',
            );
        timeSlots.value = (await response.json()).slots;
        if (
            !timeSlots.value.some((slot) => slot.starts_at === form.starts_at)
        ) {
            form.starts_at = '';
            form.ends_at = '';
        }
    } catch (error) {
        if (!abort.signal.aborted)
            lookupError.value =
                error instanceof Error
                    ? error.message
                    : 'Could not load times.';
    } finally {
        if (!abort.signal.aborted) loadingSlots.value = false;
    }
}
async function loadMembers() {
    memberAbort?.abort();
    if (
        !editorOpen.value ||
        identity.value !== 'member' ||
        !form.coach_id ||
        !form.branch_id
    )
        return;
    const abort = new AbortController();
    memberAbort = abort;
    loadingMembers.value = true;
    try {
        const response = await fetch(
            searchMembers({
                query: {
                    coach_id: form.coach_id,
                    branch_id: form.branch_id,
                    search: memberSearch.value,
                },
            }).url,
            { signal: abort.signal, headers: { Accept: 'application/json' } },
        );
        if (!response.ok)
            throw new Error(
                'Could not search members. Check the coach and branch, then try again.',
            );
        memberResults.value = (await response.json()).members;
        if (form.member_id)
            selectedMember.value =
                memberResults.value.find(
                    (m) => m.id === Number(form.member_id),
                ) ?? selectedMember.value;
    } catch (error) {
        if (!abort.signal.aborted)
            lookupError.value =
                error instanceof Error
                    ? error.message
                    : 'Could not search members.';
    } finally {
        if (!abort.signal.aborted) loadingMembers.value = false;
    }
}
watch([() => form.coach_id, () => form.branch_id, date, duration], () => {
    if (editorOpen.value) {
        loadSlots();
        loadMembers();
    }
});
watch(memberSearch, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(loadMembers, 300);
});
function changeIdentity(event: Event) {
    identity.value = (event.target as HTMLSelectElement).value as
        | 'member'
        | 'customer';
    form.member_id = '';
    form.member_purchase_id = '';
    selectedMember.value = null;
    form.customer_name = '';
    loadMembers();
}
watch(editorOpen, (open) => {
    if (!open) {
        slotAbort?.abort();
        memberAbort?.abort();
        clearTimeout(searchTimer);
    }
});
onBeforeUnmount(() => {
    slotAbort?.abort();
    memberAbort?.abort();
    clearTimeout(searchTimer);
});
function selectTime(event: Event) {
    const selected = timeSlots.value.find(
        (slot) => slot.starts_at === (event.target as HTMLSelectElement).value,
    );
    form.starts_at = selected?.starts_at ?? '';
    form.ends_at = selected?.ends_at ?? '';
}
function save() {
    const options = {
        preserveScroll: true,
        onError: focusError,
        onSuccess: () => {
            editorOpen.value = false;
        },
    };
    if (editing.value) form.patch(update(editing.value.id).url, options);
    else form.post(store().url, options);
}
const statusOpen = ref(false);
const statusBooking = ref<Booking | null>(null);
const statusForm = useForm({ status: '', revision: 1 });
const statusError = ref<HTMLElement | null>(null);
const statusLabel = (value: string) =>
    ({
        scheduled: 'Scheduled',
        completed: 'Completed',
        cancelled: 'Cancelled',
        'no-show': 'No-show',
    })[value] ?? value;
function changeStatus(row: Booking, value: string) {
    detailOpen.value = false;
    statusBooking.value = row;
    statusForm.status = value;
    statusForm.revision = row.revision;
    statusForm.clearErrors();
    statusOpen.value = true;
}
function confirmStatus() {
    if (!statusBooking.value) return;
    statusForm.patch(status(statusBooking.value.id).url, {
        preserveScroll: true,
        onError: () => nextTick(() => statusError.value?.focus()),
        onSuccess: () => {
            statusOpen.value = false;
            detailOpen.value = false;
        },
    });
}
const metrics = computed(() => [
    {
        label: "Today's appointments",
        value: props.metrics.today,
        color: 'bg-primary/5',
    },
    {
        label: 'Upcoming',
        value: props.metrics.upcoming,
        color: 'bg-blue-500/5',
    },
    {
        label: 'Completed',
        value: props.metrics.completed,
        color: 'bg-emerald-500/5',
    },
    {
        label: 'Cancelled / No-show',
        value: props.metrics.missed,
        color: 'bg-amber-500/5',
    },
]);
</script>

<template>
    <Head title="Appointments" />
    <div class="@container space-y-4 p-4 sm:p-6">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Appointments
                </h1>
                <p class="text-sm text-muted-foreground">
                    Manage bookings and follow up on scheduled sessions.
                </p>
            </div>
            <Button
                v-if="canManage"
                :disabled="!coaches.data.length || !branches.data.length"
                @click="openEditor()"
                ><Plus class="size-4" />New appointment</Button
            >
        </header>
        <div class="flex flex-wrap gap-3">
            <article
                v-for="metric in metrics"
                :key="metric.label"
                class="flex min-w-40 items-center gap-3 rounded-xl p-3"
                :class="metric.color"
            >
                <CalendarDays class="size-5 text-muted-foreground" />
                <div>
                    <p class="text-xs text-muted-foreground">
                        {{ metric.label }}
                    </p>
                    <p class="text-xl font-semibold">{{ metric.value }}</p>
                </div>
            </article>
        </div>
        <section
            class="space-y-3 rounded-xl border bg-card p-3"
            aria-label="Appointment records"
        >
            <form
                class="flex flex-wrap items-end gap-2"
                @submit.prevent="applyFilters"
            >
                <div class="w-full sm:w-56">
                    <Label for="booking-search">Search</Label
                    ><Input
                        id="booking-search"
                        v-model="filters.search"
                        placeholder="Member or customer name"
                        maxlength="100"
                    />
                </div>
                <div class="w-full sm:w-44">
                    <Label for="booking-date">Date</Label
                    ><Input
                        id="booking-date"
                        v-model="filters.date"
                        type="date"
                        min="2000-01-01"
                        max="2100-12-31"
                        @change="applyFilters"
                    />
                </div>
                <div class="w-full sm:w-40">
                    <Label for="booking-coach">Coach</Label
                    ><NativeSelect
                        id="booking-coach"
                        :value="filters.coach_id"
                        @change="
                            filters.coach_id = (
                                $event.target as HTMLSelectElement
                            ).value;
                            applyFilters();
                        "
                        ><option value="">All coaches</option>
                        <option
                            v-for="coach in coaches.data"
                            :key="coach.id"
                            :value="coach.id"
                        >
                            {{ coach.name }}
                        </option></NativeSelect
                    >
                </div>
                <div class="w-full sm:w-40">
                    <Label for="booking-branch">Branch</Label
                    ><NativeSelect
                        id="booking-branch"
                        :value="filters.branch_id"
                        @change="
                            filters.branch_id = (
                                $event.target as HTMLSelectElement
                            ).value;
                            applyFilters();
                        "
                        ><option value="">All branches</option>
                        <option
                            v-for="branch in branches.data"
                            :key="branch.id"
                            :value="branch.id"
                        >
                            {{ branch.name }}
                        </option></NativeSelect
                    >
                </div>
                <div class="w-full sm:w-44">
                    <Label for="booking-service">Service</Label
                    ><NativeSelect
                        id="booking-service"
                        :value="filters.service"
                        @change="
                            filters.service = (
                                $event.target as HTMLSelectElement
                            ).value;
                            applyFilters();
                        "
                        ><option value="">All services</option>
                        <option
                            v-for="service in services"
                            :key="service.value"
                            :value="service.value"
                        >
                            {{ service.label }}
                        </option></NativeSelect
                    >
                </div>
                <div class="w-full sm:w-36">
                    <Label for="booking-status">Status</Label
                    ><NativeSelect
                        id="booking-status"
                        :value="filters.status"
                        @change="
                            filters.status = (
                                $event.target as HTMLSelectElement
                            ).value;
                            applyFilters();
                        "
                        ><option value="">All statuses</option>
                        <option
                            v-for="value in [
                                'scheduled',
                                'completed',
                                'cancelled',
                                'no-show',
                            ]"
                            :key="value"
                            :value="value"
                        >
                            {{ statusLabel(value) }}
                        </option></NativeSelect
                    >
                </div>
                <Button variant="outline" type="submit">Search</Button>
                <Button
                    variant="ghost"
                    @click="
                        filters.date = '';
                        applyFilters();
                    "
                    >All dates</Button
                >
            </form>
            <p class="text-xs text-muted-foreground">
                Times in {{ timezone }}. Summary cards show all appointments in
                your current branch view.
            </p>
            <RecordList
                :rows="appointments.data"
                :columns="[
                    { key: 'time', label: 'Time' },
                    { key: 'customer', label: 'Member / customer' },
                    { key: 'serviceLabel', label: 'Service' },
                    { key: 'coach', label: 'Coach' },
                    { key: 'branch', label: 'Branch' },
                    { key: 'status', label: 'Status' },
                    { key: 'actions', label: 'Actions' },
                ]"
                caption="Appointments"
                row-key="id"
                wide
                empty="No appointments match these filters."
            >
                <template #cell-time="{ row }"
                    ><p class="font-medium">{{ row.time }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{ row.date }}
                    </p></template
                >
                <template #cell-status="{ row }"
                    ><span
                        class="rounded-full px-2 py-1 text-xs"
                        :class="
                            row.status === 'completed'
                                ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400'
                                : row.status === 'scheduled'
                                  ? 'bg-blue-500/15 text-blue-700 dark:text-blue-300'
                                  : 'bg-muted text-muted-foreground'
                        "
                        >{{ statusLabel(row.status) }}</span
                    ></template
                >
                <template #cell-actions="{ row }"
                    ><Button
                        variant="outline"
                        size="icon"
                        :aria-label="`View appointment: ${row.customer}`"
                        @click="view(row)"
                        ><Eye class="size-4" /></Button
                ></template>
            </RecordList>
            <nav
                class="flex flex-wrap items-center gap-2"
                aria-label="Appointment pages"
            >
                <p class="mr-auto text-xs text-muted-foreground">
                    {{ appointments.total }} records · Page
                    {{ appointments.current_page }} of
                    {{ appointments.last_page }}
                </p>
                <Button
                    v-if="appointments.prev_page_url"
                    as-child
                    variant="outline"
                    ><Link :href="appointments.prev_page_url" preserve-scroll
                        >Previous</Link
                    ></Button
                ><Button
                    v-if="appointments.next_page_url"
                    as-child
                    variant="outline"
                    ><Link :href="appointments.next_page_url" preserve-scroll
                        >Next</Link
                    ></Button
                >
            </nav>
        </section>
        <nav
            v-if="
                coaches.prev_page_url ||
                coaches.next_page_url ||
                branches.prev_page_url ||
                branches.next_page_url
            "
            class="flex flex-wrap gap-2"
            aria-label="Coach and branch options"
        >
            <Button
                v-for="[url, label] in [
                    [coaches.prev_page_url, 'Previous coaches'],
                    [coaches.next_page_url, 'More coaches'],
                    [branches.prev_page_url, 'Previous branches'],
                    [branches.next_page_url, 'More branches'],
                ]"
                v-show="url"
                :key="label ?? ''"
                as-child
                variant="outline"
                size="sm"
                ><Link :href="url ?? index().url">{{ label }}</Link></Button
            >
        </nav>
    </div>
    <Sheet v-model:open="detailOpen"
        ><SheetContent class="overflow-y-auto sm:max-w-lg">
            <SheetHeader
                ><SheetTitle>Appointment details</SheetTitle
                ><SheetDescription
                    >Booking information and status.</SheetDescription
                ></SheetHeader
            >
            <div v-if="detail" class="space-y-5 p-4">
                <dl
                    class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-3 text-sm"
                >
                    <template
                        v-for="[label, value] in [
                            ['Member / customer', detail.customer],
                            ['Service', detail.serviceLabel],
                            ...(detail.packageName
                                ? [['Training package', detail.packageName]]
                                : []),
                            ['Coach', detail.coach],
                            ['Date', detail.date],
                            ['Time', detail.time],
                            ['Branch', detail.branch],
                            ['Location', detail.location || '—'],
                            ['Status', statusLabel(detail.status)],
                            ['Notes', detail.notes || '—'],
                            ['Created', detail.createdAt],
                            ['Created by', detail.createdBy],
                        ]"
                        :key="label ?? ''"
                        ><dt class="text-muted-foreground">{{ label }}</dt>
                        <dd class="min-w-0 break-words">
                            {{ value }}
                        </dd></template
                    >
                </dl>
                <div v-if="detail.canManage" class="flex flex-wrap gap-2">
                    <Button @click="openEditor(detail)">Edit appointment</Button
                    ><Button
                        v-if="detail.hasEnded"
                        variant="outline"
                        @click="changeStatus(detail, 'completed')"
                        >Mark completed</Button
                    ><Button
                        v-if="detail.hasEnded"
                        variant="outline"
                        @click="changeStatus(detail, 'no-show')"
                        >Mark no-show</Button
                    ><Button
                        variant="outline"
                        @click="changeStatus(detail, 'cancelled')"
                        >Cancel appointment</Button
                    >
                </div>
            </div>
        </SheetContent></Sheet
    >
    <Dialog v-model:open="editorOpen"
        ><DialogScrollContent class="sm:max-w-2xl">
            <DialogHeader
                ><DialogTitle>{{
                    editing ? 'Edit appointment' : 'New appointment'
                }}</DialogTitle
                ><DialogDescription
                    >Choose a member or customer and an available time. Times in
                    {{ timezone }}.</DialogDescription
                ></DialogHeader
            >
            <form
                class="space-y-4"
                :aria-busy="form.processing"
                @submit.prevent="save"
            >
                <div
                    v-if="Object.keys(form.errors).length"
                    ref="errorSummary"
                    role="alert"
                    tabindex="-1"
                    class="rounded-lg border border-destructive p-3 text-sm text-destructive"
                >
                    <p v-for="(error, key) in form.errors" :key="key">
                        {{ error }}
                    </p>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="space-y-1">
                        <Label for="appointment-coach">Coach</Label
                        ><NativeSelect
                            id="appointment-coach"
                            :value="form.coach_id"
                            required
                            @change="
                                form.coach_id = (
                                    $event.target as HTMLSelectElement
                                ).value
                            "
                            ><option value="" disabled>Select a coach</option>
                            <option
                                v-if="
                                    editing &&
                                    !coaches.data.some(
                                        (c) => c.id === editing?.coach_id,
                                    )
                                "
                                :value="editing.coach_id"
                            >
                                {{ editing.coach }}
                            </option>
                            <option
                                v-for="coach in coaches.data"
                                :key="coach.id"
                                :value="coach.id"
                            >
                                {{ coach.name }}
                            </option></NativeSelect
                        >
                    </div>
                    <div class="space-y-1">
                        <Label for="appointment-branch">Branch</Label
                        ><NativeSelect
                            id="appointment-branch"
                            :value="form.branch_id"
                            required
                            @change="
                                form.branch_id = (
                                    $event.target as HTMLSelectElement
                                ).value
                            "
                            ><option value="" disabled>Select a branch</option>
                            <option
                                v-if="
                                    editing &&
                                    !branches.data.some(
                                        (b) => b.id === editing?.branch_id,
                                    )
                                "
                                :value="editing.branch_id"
                            >
                                {{ editing.branch }}
                            </option>
                            <option
                                v-for="branch in branches.data"
                                :key="branch.id"
                                :value="branch.id"
                            >
                                {{ branch.name }}
                            </option></NativeSelect
                        >
                    </div>
                </div>
                <div class="space-y-2">
                    <Label for="appointment-identity">Booking for</Label
                    ><NativeSelect
                        id="appointment-identity"
                        :value="identity"
                        @change="changeIdentity"
                        ><option value="member">Gym member</option>
                        <option value="customer">Customer</option></NativeSelect
                    >
                    <template v-if="identity === 'member'"
                        ><Label for="appointment-member-search"
                            >Search member</Label
                        ><Input
                            id="appointment-member-search"
                            v-model="memberSearch"
                            placeholder="Name, mobile, or member ID"
                            maxlength="100"
                        />
                        <p
                            v-if="selectedMember"
                            class="rounded-lg bg-muted p-2 text-sm"
                        >
                            Selected: {{ selectedMember.name }}
                        </p>
                        <div
                            class="max-h-36 overflow-y-auto rounded-lg border"
                            aria-label="Matching members"
                        >
                            <p
                                v-if="loadingMembers"
                                class="p-2 text-sm text-muted-foreground"
                                role="status"
                            >
                                Searching…
                            </p>
                            <button
                                v-for="member in memberResults"
                                :key="member.id"
                                type="button"
                                class="flex w-full items-center justify-between gap-2 border-b p-2 text-left text-sm hover:bg-muted focus-visible:outline-2 focus-visible:outline-ring"
                                :aria-pressed="form.member_id === member.id"
                                @click="
                                    form.member_id = member.id;
                                    form.member_purchase_id = '';
                                    selectedMember = member;
                                "
                            >
                                <span>{{ member.name }}</span
                                ><span class="text-xs text-muted-foreground"
                                    >#{{ member.id }}</span
                                >
                            </button>
                            <p
                                v-if="!loadingMembers && !memberResults.length"
                                class="p-2 text-sm text-muted-foreground"
                            >
                                No matching members.
                            </p>
                        </div></template
                    >
                    <template v-else
                        ><Label for="appointment-customer">Customer name</Label
                        ><Input
                            id="appointment-customer"
                            v-model="form.customer_name"
                            required
                            maxlength="255"
                    /></template>
                </div>
                <div
                    v-if="identity === 'member' && form.member_id"
                    class="space-y-2"
                >
                    <Label for="appointment-package"
                        >Training package (optional)</Label
                    ><NativeSelect
                        id="appointment-package"
                        v-model="form.member_purchase_id"
                        ><option value="">No prepaid package</option>
                        <option
                            v-for="p in selectedMember?.packages ?? []"
                            :key="p.id"
                            :value="p.id"
                        >
                            {{ p.name }} · {{ p.remaining }} remaining
                        </option>
                        <option
                            v-if="
                                form.member_purchase_id &&
                                !selectedMember?.packages?.some(
                                    (p) =>
                                        p.id ===
                                        Number(form.member_purchase_id),
                                )
                            "
                            :value="form.member_purchase_id"
                        >
                            Current package #{{ form.member_purchase_id }}
                        </option></NativeSelect
                    >
                    <p class="text-xs text-muted-foreground">
                        One fully paid session is used only when completed.
                    </p>
                    <InputError :message="form.errors.member_purchase_id" />
                </div>
                <div class="space-y-1">
                    <Label for="appointment-service">Service</Label
                    ><NativeSelect
                        id="appointment-service"
                        :value="form.service"
                        required
                        @change="
                            form.service = (
                                $event.target as HTMLSelectElement
                            ).value
                        "
                        ><option value="" disabled>Select a service</option>
                        <option
                            v-for="service in services"
                            :key="service.value"
                            :value="service.value"
                        >
                            {{ service.label }}
                        </option></NativeSelect
                    >
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="space-y-1">
                        <Label for="appointment-date">Date</Label
                        ><Input
                            id="appointment-date"
                            v-model="date"
                            type="date"
                            required
                            min="2000-01-01"
                            max="2100-12-31"
                        />
                    </div>
                    <div class="space-y-1">
                        <Label for="appointment-duration"
                            >Duration (minutes)</Label
                        ><Input
                            id="appointment-duration"
                            v-model="duration"
                            type="number"
                            required
                            min="15"
                            max="480"
                            step="15"
                        />
                    </div>
                </div>
                <div class="space-y-2">
                    <Label for="appointment-time">Available time</Label
                    ><NativeSelect
                        id="appointment-time"
                        :value="form.starts_at"
                        :disabled="loadingSlots"
                        required
                        @change="selectTime"
                        ><option value="" disabled>
                            {{
                                loadingSlots
                                    ? 'Loading times…'
                                    : 'Select an available time'
                            }}
                        </option>
                        <option
                            v-for="slot in timeSlots"
                            :key="slot.starts_at"
                            :value="slot.starts_at"
                        >
                            {{ slot.label }}
                        </option></NativeSelect
                    >
                    <p
                        v-if="!loadingSlots && !timeSlots.length"
                        class="text-xs text-muted-foreground"
                    >
                        No available times. Check the coach’s working hours,
                        assigned branch, and blocked time in
                        <Link
                            :href="
                                calendar({
                                    query: {
                                        coach_id: form.coach_id || undefined,
                                    },
                                })
                            "
                            class="underline"
                            >Coach calendar</Link
                        >.
                    </p>
                    <p
                        v-if="lookupError"
                        role="alert"
                        class="text-sm text-destructive"
                    >
                        {{ lookupError }}
                        <button
                            type="button"
                            class="underline"
                            @click="
                                loadSlots();
                                loadMembers();
                            "
                        >
                            Retry
                        </button>
                    </p>
                </div>
                <div class="space-y-1">
                    <Label for="appointment-location">Location (optional)</Label
                    ><Input
                        id="appointment-location"
                        v-model="form.location"
                        maxlength="100"
                        placeholder="Training area"
                    />
                </div>
                <div class="space-y-1">
                    <Label for="appointment-notes">Notes (optional)</Label
                    ><Textarea
                        id="appointment-notes"
                        v-model="form.notes"
                        maxlength="1000"
                    />
                </div>
                <div class="flex justify-end gap-2">
                    <Button
                        variant="outline"
                        :disabled="form.processing"
                        @click="editorOpen = false"
                        >Cancel</Button
                    ><Button
                        type="submit"
                        :disabled="
                            form.processing ||
                            loadingSlots ||
                            !form.starts_at ||
                            (identity === 'member' && !form.member_id)
                        "
                        >{{
                            form.processing
                                ? 'Saving…'
                                : editing
                                  ? 'Save appointment'
                                  : 'Confirm appointment'
                        }}</Button
                    >
                </div>
            </form>
        </DialogScrollContent></Dialog
    >
    <Dialog v-model:open="statusOpen"
        ><DialogScrollContent
            ><DialogHeader
                ><DialogTitle
                    >{{
                        statusLabel(statusForm.status)
                    }}
                    appointment?</DialogTitle
                ><DialogDescription
                    >{{ statusBooking?.customer }} · {{ statusBooking?.date }} ·
                    {{ statusBooking?.time }}. This status is final and its
                    history is retained.</DialogDescription
                ></DialogHeader
            >
            <div
                v-if="Object.keys(statusForm.errors).length"
                ref="statusError"
                role="alert"
                tabindex="-1"
            >
                <InputError
                    v-for="(error, key) in statusForm.errors"
                    :key="key"
                    :message="error"
                />
            </div>
            <div class="flex justify-end gap-2">
                <Button
                    variant="outline"
                    :disabled="statusForm.processing"
                    @click="statusOpen = false"
                    >Back</Button
                ><Button
                    :disabled="statusForm.processing"
                    @click="confirmStatus"
                    >{{
                        statusForm.processing ? 'Saving…' : 'Confirm status'
                    }}</Button
                >
            </div></DialogScrollContent
        ></Dialog
    >
</template>
