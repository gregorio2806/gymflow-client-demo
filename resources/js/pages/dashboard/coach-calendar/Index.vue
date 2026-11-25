<script setup lang="ts">
import RecordList from '@/components/RecordList.vue';
import { formatDisplayDate } from '@/lib/date-format';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import {
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    CircleX,
    Clock3,
    MapPin,
    Pencil,
    Plus,
    RefreshCw,
} from '@lucide/vue';
import {
    index,
    store,
    update,
    cancel,
    availability as saveAvailability,
} from '@/routes/coach-calendar';
import { index as appointmentsIndex } from '@/routes/appointments';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import NativeSelect from '@/components/NativeSelect.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Spinner } from '@/components/ui/spinner';
import {
    Dialog,
    DialogScrollContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
type Coach = { id: number; name: string };
type Schedule = {
    id: number;
    kind: string;
    status: string;
    customer: string | null;
    location: string | null;
    service: string | null;
    createdAt: string;
    createdBy: string;
    title: string;
    branch: string;
    startsAt: string;
    endsAt: string;
    startTime: string;
    endTime: string;
    dateRange: string;
    is_cancelled: boolean;
    revision: number;
    canEdit: boolean;
    canCancel: boolean;
    edit: {
        branch_id: number;
        notes: string | null;
        starts_at: string;
        ends_at: string;
        revision: number;
    } | null;
};
const props = defineProps<{
    coaches: {
        data: Coach[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    selectedCoach: Coach | null;
    canSelectCoach: boolean;
    month: {
        value: string;
        label: string;
        offset: number;
        previous: string | null;
        next: string | null;
    };
    view: 'month' | 'week' | 'day' | 'list';
    availability: { enabled: boolean; start: string; end: string }[];
    availabilityRevision: number;
    canEditAvailability: boolean;
    today: string;
    selectedDay: string;
    days: {
        date: string;
        number: number;
        count: number;
        services: { value: string; label: string; count: number }[];
        today: boolean;
        selected: boolean;
    }[];
    schedules: {
        data: Schedule[];
        total: number;
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    } | null;
    branches: {
        data: { id: number; name: string }[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    canAdd: boolean;
    requestKey: string;
    timezone: string;
}>();
defineOptions({
    layout: { breadcrumbs: [{ title: 'Coach calendar', href: index() }] },
});
const coachId = ref<number | string>(props.selectedCoach?.id ?? '');
const viewMode = ref(props.view);
watch(
    () => props.view,
    (view) => {
        viewMode.value = view;
    },
);
const weekdays = [
    'Sunday',
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
];
const availabilityOpen = ref(false);
const hoursForm = useForm({
    hours: props.availability.map((day) => ({ ...day })),
    revision: props.availabilityRevision,
});
const hoursAlert = ref<HTMLElement | null>(null);
function editAvailability() {
    hoursForm.hours = props.availability.map((day) => ({ ...day }));
    hoursForm.revision = props.availabilityRevision;
    hoursForm.clearErrors();
    availabilityOpen.value = true;
}
function submitHours() {
    if (!props.selectedCoach) return;
    hoursForm.put(saveAvailability(props.selectedCoach.id).url, {
        preserveScroll: true,
        onError: () => nextTick(() => hoursAlert.value?.focus()),
        onSuccess: () => {
            availabilityOpen.value = false;
        },
    });
}
function setView(view: typeof props.view) {
    visit(props.month.value, props.selectedDay, view);
}
function shift(direction: number) {
    if (viewMode.value === 'month') {
        const month = direction < 0 ? props.month.previous : props.month.next;
        if (month) visit(month);
        return;
    }
    const at = new Date(props.selectedDay + 'T12:00:00Z');
    at.setUTCDate(
        at.getUTCDate() + direction * (viewMode.value === 'week' ? 7 : 1),
    );
    const day = at.toISOString().slice(0, 10);
    if (day >= '1000-01-01' && day <= '9998-12-31') visit(day.slice(0, 7), day);
}
const serviceColor = (value: string) =>
    ({
        'personal-training': 'bg-primary',
        'group-session': 'bg-blue-500',
        'fitness-assessment': 'bg-emerald-500',
        consultation: 'bg-violet-500',
    })[value] ?? 'bg-muted-foreground';
const statusLabel = (schedule: Schedule) =>
    schedule.kind === 'block'
        ? schedule.is_cancelled
            ? 'Cancelled'
            : 'Blocked'
        : {
              scheduled: 'Scheduled',
              completed: 'Completed',
              cancelled: 'Cancelled',
              'no-show': 'No-show',
          }[schedule.status];
const adding = ref(false);
const editingId = ref<number | null>(null);
const cancelling = ref<Schedule | null>(null);
const cancelOpen = ref(false);
const cancelForm = useForm({ revision: 1, month: '', day: '' });
const formAlert = ref<HTMLElement | null>(null);
const cancelAlert = ref<HTMLElement | null>(null);
const dayHeading = ref<HTMLElement | null>(null);
const branchLabel = ref('');
const form = useForm({
    coach_id: 0,
    branch_id: '',
    title: '',
    starts_at: '',
    ends_at: '',
    notes: '',
    request_key: '',
    month: '',
    day: '',
    revision: 1,
});
const generalError = computed(
    () =>
        form.errors.coach_id ||
        form.errors.revision ||
        form.errors.request_key ||
        form.errors.month ||
        form.errors.day,
);
const cancellationError = computed(
    () =>
        cancelForm.errors.revision ||
        cancelForm.errors.month ||
        cancelForm.errors.day,
);
function focusFormError() {
    nextTick(() => {
        const fields = {
            title: 'schedule-title',
            branch_id: 'schedule-branch',
            starts_at: 'schedule-start',
            ends_at: 'schedule-end',
            notes: 'schedule-notes',
        };
        const first = Object.entries(fields).find(
            ([key]) => form.errors[key as keyof typeof form.errors],
        );
        if (first) document.getElementById(first[1])?.focus();
        else formAlert.value?.focus();
    });
}
function openAdd() {
    editingId.value = null;
    form.defaults({
        coach_id: props.selectedCoach?.id ?? 0,
        branch_id: String(props.branches.data[0]?.id ?? ''),
        title: '',
        starts_at: `${props.selectedDay}T09:00`,
        ends_at: `${props.selectedDay}T10:00`,
        notes: '',
        request_key: props.requestKey,
        month: props.month.value,
        day: props.selectedDay,
        revision: 1,
    });
    form.reset();
    form.clearErrors();
    branchLabel.value = props.branches.data[0]?.name ?? '';
    adding.value = true;
}
function save() {
    const options = {
        preserveScroll: true,
        onError: focusFormError,
        onSuccess: () => {
            adding.value = false;
            form.reset();
        },
    };
    if (editingId.value) form.patch(update(editingId.value).url, options);
    else form.post(store().url, options);
}
function openEdit(schedule: Schedule) {
    if (!schedule.edit) return;
    editingId.value = schedule.id;
    form.defaults({
        coach_id: props.selectedCoach?.id ?? 0,
        branch_id: String(schedule.edit.branch_id),
        title: schedule.title,
        starts_at: schedule.edit.starts_at,
        ends_at: schedule.edit.ends_at,
        notes: schedule.edit.notes ?? '',
        request_key: '',
        month: props.month.value,
        day: props.selectedDay,
        revision: schedule.revision,
    });
    form.reset();
    form.clearErrors();
    branchLabel.value = schedule.branch;
    adding.value = true;
}
function openCancel(schedule: Schedule) {
    cancelling.value = schedule;
    cancelForm.defaults({
        revision: schedule.revision,
        month: props.month.value,
        day: props.selectedDay,
    });
    cancelForm.reset();
    cancelForm.clearErrors();
    cancelOpen.value = true;
}
function confirmCancel() {
    if (!cancelling.value) return;
    cancelForm.post(cancel(cancelling.value.id).url, {
        preserveScroll: true,
        onError: () => nextTick(() => cancelAlert.value?.focus()),
        onSuccess: () => {
            cancelOpen.value = false;
        },
    });
}
function changeBranch(event: Event) {
    const select = event.target as HTMLSelectElement;
    form.branch_id = select.value;
    branchLabel.value = select.selectedOptions[0]?.text ?? '';
}
watch(
    () => props.selectedCoach,
    (coach) => {
        coachId.value = coach?.id ?? '';
    },
);
function visit(month: string, day?: string, view = viewMode.value) {
    router.get(
        index().url,
        {
            month,
            view,
            ...(coachId.value ? { coach_id: coachId.value } : {}),
            ...(day ? { day } : {}),
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (day) {
                    dayHeading.value?.focus({ preventScroll: true });
                    if (window.matchMedia('(max-width: 767px)').matches)
                        dayHeading.value?.scrollIntoView({
                            behavior: window.matchMedia(
                                '(prefers-reduced-motion: reduce)',
                            ).matches
                                ? 'auto'
                                : 'smooth',
                            block: 'start',
                        });
                }
            },
        },
    );
}
function changeCoach(event: Event) {
    coachId.value = (event.target as HTMLSelectElement).value;
    visit(props.month.value, props.selectedDay);
}
function changeMonth(event: Event) {
    const value = (event.target as HTMLInputElement).value;
    if (value && value >= '1000-01' && value <= '9998-12') visit(value);
}
function changeDay(event: Event) {
    const value = (event.target as HTMLInputElement).value;
    if (value && value >= '1000-01-01' && value <= '9998-12-31')
        visit(value.slice(0, 7), value);
}
</script>
<template>
    <Head title="Coach calendar" />
    <div class="@container space-y-4 p-4 sm:p-6">
        <header class="space-y-1">
            <h1 class="text-2xl font-semibold tracking-tight">
                Coach calendar
            </h1>
            <p class="text-sm text-muted-foreground">
                View assigned appointments, set working hours, and block
                unavailable time.
            </p>
        </header>

        <section
            aria-label="Calendar controls"
            class="space-y-3 rounded-xl border bg-card p-3"
        >
            <div class="flex flex-wrap items-center gap-2">
                <div v-if="canSelectCoach" class="w-full sm:w-60">
                    <Label for="calendar-coach" class="sr-only">Coach</Label>
                    <NativeSelect
                        id="calendar-coach"
                        :value="coachId"
                        :disabled="!coaches.data.length"
                        @change="changeCoach"
                    >
                        <option v-if="!selectedCoach" value="">
                            No active coaches available
                        </option>
                        <option
                            v-else-if="
                                !coaches.data.some(
                                    (coach) => coach.id === selectedCoach?.id,
                                )
                            "
                            :value="selectedCoach.id"
                        >
                            {{ selectedCoach.name }}
                        </option>
                        <option
                            v-for="coach in coaches.data"
                            :key="coach.id"
                            :value="coach.id"
                        >
                            {{ coach.name }}
                        </option>
                    </NativeSelect>
                </div>
                <p v-else-if="selectedCoach" class="mr-2 text-sm font-medium">
                    {{ selectedCoach.name }}
                </p>
                <div class="flex flex-wrap items-center gap-2">
                    <Label
                        :for="
                            viewMode === 'month'
                                ? 'calendar-month'
                                : 'calendar-day'
                        "
                        class="sr-only"
                        >{{ viewMode === 'month' ? 'Month' : 'Day' }}</Label
                    >
                    <Input
                        v-if="viewMode === 'month'"
                        id="calendar-month"
                        type="month"
                        :model-value="month.value"
                        min="1000-01"
                        max="9998-12"
                        class="w-44 [color-scheme:light] dark:[color-scheme:dark]"
                        @change="changeMonth"
                    />
                    <Input
                        v-else
                        id="calendar-day"
                        type="date"
                        :model-value="selectedDay"
                        min="1000-01-01"
                        max="9998-12-31"
                        class="w-44 [color-scheme:light] dark:[color-scheme:dark]"
                        @change="changeDay"
                    />
                    <Button
                        variant="outline"
                        size="icon"
                        aria-label="Previous period"
                        :disabled="!month.previous"
                        @click="shift(-1)"
                        ><ChevronLeft class="size-4"
                    /></Button>
                    <Button
                        variant="outline"
                        size="icon"
                        aria-label="Next period"
                        :disabled="!month.next"
                        @click="shift(1)"
                        ><ChevronRight class="size-4"
                    /></Button>
                    <Button
                        variant="outline"
                        @click="visit(today.slice(0, 7), today)"
                        >Today</Button
                    >
                </div>
                <div class="flex flex-wrap items-center gap-2 sm:ml-auto">
                    <div
                        class="flex rounded-lg border bg-muted/30 p-1"
                        role="group"
                        aria-label="Calendar view"
                    >
                        <Button
                            v-for="view in [
                                'month',
                                'week',
                                'day',
                                'list',
                            ] as const"
                            :key="view"
                            :variant="viewMode === view ? 'secondary' : 'ghost'"
                            size="sm"
                            :aria-pressed="viewMode === view"
                            @click="setView(view)"
                            >{{
                                view.charAt(0).toUpperCase() + view.slice(1)
                            }}</Button
                        >
                    </div>
                    <Button
                        variant="outline"
                        size="icon"
                        aria-label="Refresh calendar"
                        @click="visit(month.value, selectedDay)"
                        ><RefreshCw class="size-4"
                    /></Button>
                    <Button
                        v-if="canEditAvailability"
                        variant="outline"
                        @click="editAvailability"
                        >Edit availability</Button
                    >
                    <Button v-if="canAdd" @click="openAdd"
                        ><Plus class="size-4" />Block time</Button
                    >
                </div>
            </div>
            <nav
                v-if="
                    canSelectCoach &&
                    (coaches.prev_page_url || coaches.next_page_url)
                "
                aria-label="Coach pages"
                class="flex flex-wrap gap-2"
            >
                <Button
                    v-if="coaches.prev_page_url"
                    as-child
                    variant="outline"
                    size="sm"
                    ><Link :href="coaches.prev_page_url" preserve-state
                        >Previous coaches</Link
                    ></Button
                >
                <Button
                    v-if="coaches.next_page_url"
                    as-child
                    variant="outline"
                    size="sm"
                    ><Link :href="coaches.next_page_url" preserve-state
                        >More coaches</Link
                    ></Button
                >
            </nav>
        </section>

        <div
            v-if="
                selectedCoach && (viewMode === 'month' || viewMode === 'week')
            "
            class="grid items-start gap-4 @min-[54rem]:grid-cols-[minmax(0,1fr)_20rem]"
        >
            <section
                aria-label="Month calendar"
                class="min-w-0 space-y-3 rounded-xl border bg-card px-0 py-3 sm:p-4"
            >
                <div
                    class="flex flex-wrap items-center justify-between gap-2 px-3 sm:px-0"
                >
                    <h2 aria-live="polite" class="text-base font-semibold">
                        {{
                            viewMode === 'week'
                                ? 'Week of ' +
                                  formatDisplayDate(
                                      days[0]?.date || selectedDay,
                                  )
                                : month.label
                        }}
                    </h2>
                    <span class="text-xs text-muted-foreground">{{
                        selectedCoach.name
                    }}</span>
                </div>
                <div class="grid grid-cols-7 sm:gap-1" data-month-calendar>
                    <div
                        v-for="weekday in [
                            'Sun',
                            'Mon',
                            'Tue',
                            'Wed',
                            'Thu',
                            'Fri',
                            'Sat',
                        ]"
                        :key="weekday"
                        class="py-2 text-center text-xs font-medium text-muted-foreground"
                    >
                        {{ weekday }}
                    </div>
                    <div
                        v-for="blank in viewMode === 'month' ? month.offset : 0"
                        :key="`before-${blank}`"
                        aria-hidden="true"
                        class="rounded-lg bg-muted/30"
                    />
                    <button
                        v-for="day in days"
                        :key="day.date"
                        type="button"
                        class="flex min-h-16 min-w-0 flex-col items-center gap-1 rounded-lg border p-1 text-sm transition-colors hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring sm:min-h-20 sm:items-start sm:p-2"
                        :class="
                            day.selected
                                ? 'border-primary bg-primary/5'
                                : 'border-border'
                        "
                        :aria-label="`${day.date}: ${day.count} scheduled sessions${day.today ? ', today' : ''}`"
                        :aria-pressed="day.selected"
                        :aria-current="day.today ? 'date' : undefined"
                        @click="visit(day.date.slice(0, 7), day.date)"
                    >
                        <span
                            class="flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold"
                            :class="
                                day.selected
                                    ? 'bg-primary text-primary-foreground'
                                    : day.today
                                      ? 'ring-1 ring-primary'
                                      : ''
                            "
                            >{{ day.number }}</span
                        >
                        <span
                            v-if="day.count"
                            class="flex items-center gap-1 text-xs text-muted-foreground"
                            aria-hidden="true"
                            ><span
                                class="size-1.5 shrink-0 rounded-full bg-primary"
                            />{{ day.count
                            }}<span class="hidden sm:inline">{{
                                day.count === 1 ? 'session' : 'sessions'
                            }}</span></span
                        >
                        <span
                            v-for="service in day.services.filter(
                                (s) => s.count > 0,
                            )"
                            :key="service.value"
                            class="flex min-w-0 items-center gap-1 text-xs"
                            ><span
                                class="size-1.5 shrink-0 rounded-full"
                                :class="serviceColor(service.value)"
                            /><span class="hidden truncate sm:inline"
                                >{{ service.count }} {{ service.label }}</span
                            ></span
                        >
                    </button>
                    <div
                        v-for="blank in viewMode === 'month'
                            ? (7 - ((month.offset + days.length) % 7)) % 7
                            : 0"
                        :key="`after-${blank}`"
                        aria-hidden="true"
                        class="rounded-lg bg-muted/30"
                    />
                </div>
                <p
                    class="flex items-center gap-2 px-3 text-xs text-muted-foreground sm:px-0"
                >
                    <span
                        class="size-1.5 shrink-0 rounded-full bg-primary"
                    />Appointments and blocked time. Select a day to view its
                    records.
                </p>
            </section>

            <aside
                aria-label="Selected day sessions"
                class="space-y-3 rounded-xl border bg-card p-4"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold">
                        {{ formatDisplayDate(selectedDay) }}
                    </h2>
                    <span
                        class="rounded-full bg-muted px-2 py-1 text-xs text-muted-foreground"
                        >{{ schedules?.total ?? 0 }}
                        {{
                            schedules?.total === 1 ? 'record' : 'records'
                        }}</span
                    >
                </div>
                <p
                    v-if="!schedules?.data.length"
                    class="py-6 text-center text-sm text-muted-foreground"
                >
                    No sessions on this day.
                </p>
                <article
                    v-for="schedule in schedules?.data.slice(0, 3) ?? []"
                    :key="schedule.id"
                    class="flex gap-3 rounded-lg border border-l-4 p-3"
                    :class="
                        schedule.is_cancelled
                            ? 'border-l-muted-foreground'
                            : schedule.service === 'group-session'
                              ? 'border-l-blue-500'
                              : schedule.service === 'fitness-assessment'
                                ? 'border-l-emerald-500'
                                : schedule.service === 'consultation'
                                  ? 'border-l-violet-500'
                                  : 'border-l-primary'
                    "
                >
                    <div
                        class="shrink-0 border-r pr-3 text-xs"
                        :title="`${formatDisplayDate(schedule.startsAt)} - ${formatDisplayDate(schedule.endsAt)}`"
                    >
                        <span class="sr-only">{{ schedule.dateRange }}</span>
                        <p class="font-semibold">{{ schedule.startTime }}</p>
                        <p class="mt-1 text-muted-foreground">
                            {{ schedule.endTime }}
                        </p>
                    </div>
                    <div class="min-w-0 space-y-2">
                        <h3 class="text-sm font-medium break-words">
                            {{ schedule.title }}
                        </h3>
                        <p
                            v-if="schedule.customer"
                            class="text-xs text-muted-foreground"
                        >
                            {{ schedule.customer }}
                        </p>
                        <p
                            v-if="schedule.location"
                            class="text-xs text-muted-foreground"
                        >
                            {{ schedule.location }}
                        </p>
                        <Link
                            v-if="schedule.kind === 'appointment'"
                            :href="
                                appointmentsIndex({
                                    query: { appointment: schedule.id },
                                })
                            "
                            class="text-xs font-medium text-primary underline"
                            >View appointment</Link
                        >
                        <p
                            v-if="schedule.dateRange.includes(' - ')"
                            class="text-xs text-muted-foreground"
                        >
                            {{ schedule.dateRange }}
                        </p>
                        <p
                            class="flex items-start gap-1 text-xs text-muted-foreground"
                        >
                            <MapPin class="mt-0.5 size-3 shrink-0" />{{
                                schedule.branch
                            }}
                        </p>
                        <span
                            class="inline-flex rounded-full px-2 py-0.5 text-xs"
                            :class="
                                schedule.is_cancelled
                                    ? 'bg-muted text-muted-foreground'
                                    : 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400'
                            "
                            >{{ statusLabel(schedule) }}</span
                        >
                    </div>
                </article>
                <a
                    v-if="(schedules?.total ?? 0) > 3"
                    href="#day-schedules-heading"
                    class="block text-center text-sm font-medium text-primary underline-offset-4 hover:underline"
                    >View all {{ schedules?.total }} records</a
                >
                <Button
                    v-if="canAdd"
                    variant="outline"
                    class="w-full"
                    @click="openAdd"
                    ><Plus class="size-4" />Block time</Button
                >
                <p
                    class="flex items-center justify-center gap-1 text-xs text-muted-foreground"
                >
                    <Clock3 class="size-3" />{{ timezone }}
                </p>
            </aside>
        </div>
        <p
            v-if="!selectedCoach"
            class="rounded-xl border bg-card p-8 text-center text-sm text-muted-foreground"
        >
            No active coaches available.
        </p>

        <section
            v-if="selectedCoach"
            aria-labelledby="day-schedules-heading"
            class="min-w-0 space-y-3 rounded-xl border bg-card p-4"
        >
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2
                    id="day-schedules-heading"
                    ref="dayHeading"
                    tabindex="-1"
                    aria-live="polite"
                    class="scroll-mt-20 text-base font-semibold"
                >
                    Schedules for {{ formatDisplayDate(selectedDay) }}
                </h2>
                <span
                    class="rounded-full bg-muted px-2 py-1 text-xs text-muted-foreground"
                    >{{ schedules?.total ?? 0 }}
                    {{ schedules?.total === 1 ? 'record' : 'records' }}</span
                >
            </div>
            <p class="text-xs text-muted-foreground">
                Times shown in {{ timezone }}. Overnight entries appear on each
                day they overlap.
            </p>
            <div
                v-if="viewMode === 'day'"
                role="region"
                aria-label="Day sessions"
                class="grid gap-3 sm:grid-cols-2"
            >
                <p
                    v-if="!schedules?.data.length"
                    class="p-4 text-sm text-muted-foreground"
                >
                    No sessions on this day.
                </p>
                <article
                    v-for="schedule in schedules?.data ?? []"
                    :key="schedule.id"
                    class="space-y-2 rounded-lg border p-3"
                >
                    <p class="text-sm font-semibold">
                        {{ schedule.startTime }} - {{ schedule.endTime }}
                    </p>
                    <h3 class="text-sm font-medium">{{ schedule.title }}</h3>
                    <p v-if="schedule.customer" class="text-sm">
                        {{ schedule.customer }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ schedule.branch
                        }}<span v-if="schedule.location">
                            - {{ schedule.location }}</span
                        >
                    </p>
                    <p class="text-xs">{{ statusLabel(schedule) }}</p>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-if="schedule.kind === 'appointment'"
                            as-child
                            variant="outline"
                            size="sm"
                            ><Link
                                :href="
                                    appointmentsIndex({
                                        query: { appointment: schedule.id },
                                    })
                                "
                                >View appointment</Link
                            ></Button
                        ><Button
                            v-if="schedule.canEdit"
                            variant="outline"
                            size="sm"
                            @click="openEdit(schedule)"
                            >Edit blocked time</Button
                        ><Button
                            v-if="schedule.canCancel"
                            variant="outline"
                            size="sm"
                            @click="openCancel(schedule)"
                            >Cancel blocked time</Button
                        >
                    </div>
                </article>
            </div>
            <RecordList
                v-else
                :rows="schedules?.data ?? []"
                :columns="[
                    { key: 'title', label: 'Schedule', width: '30%' },
                    { key: 'time', label: 'Time', width: '25%' },
                    { key: 'branch', label: 'Branch' },
                    { key: 'status', label: 'Status', width: '15%' },
                    { key: 'actions', label: 'Actions', width: '15%' },
                ]"
                caption="Coach schedules for the selected day"
                row-key="id"
                empty="No schedules on this day."
            >
                <template #cell-title="{ row: schedule }"
                    ><p class="font-medium">{{ schedule.title }}</p>
                    <p
                        v-if="schedule.customer"
                        class="text-xs text-muted-foreground"
                    >
                        {{ schedule.customer }}
                    </p>
                    <p
                        v-if="schedule.location"
                        class="text-xs text-muted-foreground"
                    >
                        {{ schedule.location }}
                    </p></template
                >
                <template #cell-time="{ row: schedule }"
                    ><p class="font-medium">
                        {{ schedule.startTime }} - {{ schedule.endTime }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ schedule.dateRange }}
                    </p></template
                >
                <template #cell-status="{ row: schedule }"
                    ><span
                        class="inline-flex rounded-full px-2 py-1 text-xs"
                        :class="
                            schedule.is_cancelled
                                ? 'bg-muted text-muted-foreground'
                                : 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400'
                        "
                        >{{ statusLabel(schedule) }}</span
                    ></template
                >
                <template #cell-actions="{ row: schedule }"
                    ><div class="flex justify-end gap-2">
                        <Button
                            v-if="schedule.kind === 'appointment'"
                            as-child
                            variant="outline"
                            size="sm"
                            ><Link
                                :href="
                                    appointmentsIndex({
                                        query: { appointment: schedule.id },
                                    })
                                "
                                >View appointment</Link
                            ></Button
                        >
                        <Button
                            v-if="schedule.canEdit"
                            variant="outline"
                            size="icon"
                            :aria-label="'Edit blocked time: ' + schedule.title"
                            @click="openEdit(schedule)"
                            ><Pencil class="size-4" /></Button
                        ><Button
                            v-if="schedule.canCancel"
                            variant="outline"
                            size="icon"
                            :aria-label="
                                'Cancel blocked time: ' + schedule.title
                            "
                            @click="openCancel(schedule)"
                            ><CircleX class="size-4"
                        /></Button></div
                ></template>
            </RecordList>
            <nav
                v-if="schedules && schedules.last_page > 1"
                aria-label="Schedule pages"
                class="flex flex-wrap items-center gap-3"
            >
                <Button
                    v-if="schedules.prev_page_url"
                    as-child
                    variant="outline"
                    ><Link
                        :href="schedules.prev_page_url"
                        preserve-state
                        preserve-scroll
                        >Previous schedules</Link
                    ></Button
                >
                <span class="text-sm text-muted-foreground"
                    >Page {{ schedules.current_page }} of
                    {{ schedules.last_page }}</span
                >
                <Button
                    v-if="schedules.next_page_url"
                    as-child
                    variant="outline"
                    ><Link
                        :href="schedules.next_page_url"
                        preserve-state
                        preserve-scroll
                        >Next schedules</Link
                    ></Button
                >
            </nav>
        </section>

        <section
            v-if="selectedCoach"
            aria-label="Coach availability"
            class="space-y-3 rounded-xl border bg-card p-4"
        >
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-base font-semibold">Coach availability</h2>
                <Button
                    v-if="canEditAvailability"
                    variant="outline"
                    size="sm"
                    @click="editAvailability"
                    >Edit working hours</Button
                >
            </div>
            <p class="text-xs text-muted-foreground">
                Working hours apply across assigned branches. Use Block time for
                breaks or leave. Changing hours keeps existing bookings.
            </p>
            <dl class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    v-for="(hours, i) in availability"
                    :key="i"
                    class="flex justify-between gap-2 rounded-lg bg-muted/30 p-2 text-xs"
                >
                    <dt>{{ weekdays[i] }}</dt>
                    <dd>
                        {{
                            hours.enabled
                                ? hours.start + ' - ' + hours.end
                                : 'Unavailable'
                        }}
                    </dd>
                </div>
            </dl>
        </section>
        <Dialog v-model:open="availabilityOpen"
            ><DialogScrollContent
                ><DialogHeader
                    ><DialogTitle>Edit availability</DialogTitle
                    ><DialogDescription
                        >{{ selectedCoach?.name }} - {{ timezone }}. Enable the
                        coach's working days and hours.</DialogDescription
                    ></DialogHeader
                >
                <form class="space-y-4" @submit.prevent="submitHours">
                    <div
                        v-if="Object.keys(hoursForm.errors).length"
                        ref="hoursAlert"
                        tabindex="-1"
                        role="alert"
                    >
                        <InputError
                            v-for="(error, key) in hoursForm.errors"
                            :key="key"
                            :message="error"
                        />
                    </div>
                    <div
                        v-for="(hours, i) in hoursForm.hours"
                        :key="i"
                        class="grid grid-cols-2 items-center gap-2 sm:grid-cols-[minmax(0,1fr)_6rem_6rem]"
                    >
                        <label
                            class="col-span-2 flex items-center gap-2 text-sm sm:col-span-1"
                            ><input
                                v-model="hours.enabled"
                                type="checkbox"
                                class="size-4 accent-primary"
                            />{{ weekdays[i] }}</label
                        ><Input
                            v-model="hours.start"
                            type="time"
                            :disabled="!hours.enabled"
                            :aria-label="weekdays[i] + ' start'"
                            required
                        /><Input
                            v-model="hours.end"
                            type="time"
                            :disabled="!hours.enabled"
                            :aria-label="weekdays[i] + ' end'"
                            required
                        />
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button
                            variant="outline"
                            :disabled="hoursForm.processing"
                            @click="availabilityOpen = false"
                            >Cancel</Button
                        ><Button
                            type="submit"
                            :disabled="hoursForm.processing"
                            >{{
                                hoursForm.processing
                                    ? 'Saving...'
                                    : 'Save availability'
                            }}</Button
                        >
                    </div>
                </form>
            </DialogScrollContent></Dialog
        >
        <Dialog v-model:open="adding">
            <DialogScrollContent
                class="[&>button]:inline-flex [&>button]:size-11 [&>button]:items-center [&>button]:justify-center"
            >
                <DialogHeader class="pr-12">
                    <DialogTitle>{{
                        editingId ? 'Edit blocked time' : 'Block time'
                    }}</DialogTitle>
                    <DialogDescription
                        >{{ selectedCoach?.name }} &middot; {{ timezone }}.
                        Title, branch, start, and end are
                        required.</DialogDescription
                    >
                </DialogHeader>
                <form
                    class="space-y-4"
                    :aria-busy="form.processing"
                    @submit.prevent="save"
                >
                    <div class="space-y-2">
                        <Label for="schedule-title">Title</Label>
                        <Input
                            id="schedule-title"
                            v-model="form.title"
                            :aria-invalid="!!form.errors.title"
                            :aria-describedby="
                                form.errors.title
                                    ? 'schedule-title-error'
                                    : undefined
                            "
                            maxlength="100"
                            required
                        />
                        <InputError
                            id="schedule-title-error"
                            :message="form.errors.title"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="schedule-branch">Branch</Label>
                        <NativeSelect
                            id="schedule-branch"
                            :value="form.branch_id"
                            :aria-invalid="!!form.errors.branch_id"
                            :aria-describedby="
                                form.errors.branch_id
                                    ? 'schedule-branch-error'
                                    : undefined
                            "
                            required
                            @change="changeBranch"
                        >
                            <option value="" disabled>Choose a branch</option>
                            <option
                                v-if="
                                    form.branch_id &&
                                    !branches.data.some(
                                        (branch) =>
                                            String(branch.id) ===
                                            form.branch_id,
                                    )
                                "
                                :value="form.branch_id"
                            >
                                {{ branchLabel }}
                            </option>
                            <option
                                v-for="branch in branches.data"
                                :key="branch.id"
                                :value="branch.id"
                            >
                                {{ branch.name }}
                            </option>
                        </NativeSelect>
                        <InputError
                            id="schedule-branch-error"
                            :message="form.errors.branch_id"
                        />
                        <div
                            v-if="
                                branches.prev_page_url || branches.next_page_url
                            "
                            class="flex flex-wrap gap-2"
                        >
                            <Button
                                v-if="branches.prev_page_url"
                                as-child
                                variant="outline"
                                ><Link
                                    :href="branches.prev_page_url"
                                    preserve-state
                                    preserve-scroll
                                    >Previous branches</Link
                                ></Button
                            >
                            <Button
                                v-if="branches.next_page_url"
                                as-child
                                variant="outline"
                                ><Link
                                    :href="branches.next_page_url"
                                    preserve-state
                                    preserve-scroll
                                    >More branches</Link
                                ></Button
                            >
                        </div>
                    </div>
                    <div class="space-y-2">
                        <Label for="schedule-start">Start</Label>
                        <Input
                            id="schedule-start"
                            v-model="form.starts_at"
                            :aria-invalid="!!form.errors.starts_at"
                            :aria-describedby="
                                form.errors.starts_at
                                    ? 'schedule-start-error'
                                    : undefined
                            "
                            type="datetime-local"
                            required
                        />
                        <InputError
                            id="schedule-start-error"
                            :message="form.errors.starts_at"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="schedule-end">End</Label>
                        <Input
                            id="schedule-end"
                            v-model="form.ends_at"
                            :aria-invalid="!!form.errors.ends_at"
                            :aria-describedby="
                                form.errors.ends_at
                                    ? 'schedule-end-error'
                                    : undefined
                            "
                            type="datetime-local"
                            required
                        />
                        <InputError
                            id="schedule-end-error"
                            :message="form.errors.ends_at"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="schedule-notes">Notes (optional)</Label>
                        <Textarea
                            id="schedule-notes"
                            v-model="form.notes"
                            :aria-invalid="!!form.errors.notes"
                            :aria-describedby="
                                form.errors.notes
                                    ? 'schedule-notes-help schedule-notes-error'
                                    : 'schedule-notes-help'
                            "
                            maxlength="1000"
                        />
                        <p
                            id="schedule-notes-help"
                            class="text-sm text-muted-foreground"
                        >
                            Use general scheduling notes. Leave out sensitive
                            health information.
                        </p>
                        <InputError
                            id="schedule-notes-error"
                            :message="form.errors.notes"
                        />
                    </div>
                    <div
                        v-if="generalError"
                        ref="formAlert"
                        tabindex="-1"
                        role="alert"
                    >
                        <InputError :message="generalError" />
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            type="submit"
                            :disabled="form.processing"
                            :aria-busy="form.processing"
                            ><Spinner
                                v-if="form.processing"
                                class="size-4"
                            /><span :class="form.processing ? 'sr-only' : ''">{{
                                form.processing
                                    ? 'Saving schedule'
                                    : 'Save schedule'
                            }}</span></Button
                        >
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="form.processing"
                            @click="adding = false"
                            >Close</Button
                        >
                    </div>
                </form>
            </DialogScrollContent>
        </Dialog>
        <Dialog v-model:open="cancelOpen">
            <DialogScrollContent
                class="[&>button]:inline-flex [&>button]:size-11 [&>button]:items-center [&>button]:justify-center"
            >
                <DialogHeader class="pr-12">
                    <DialogTitle>Cancel blocked time?</DialogTitle>
                    <DialogDescription
                        >{{ cancelling?.title }} will be marked cancelled. Its
                        history is kept, and the time becomes
                        available.</DialogDescription
                    >
                </DialogHeader>
                <div
                    v-if="cancellationError"
                    ref="cancelAlert"
                    tabindex="-1"
                    role="alert"
                >
                    <InputError :message="cancellationError" />
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button
                        variant="destructive"
                        :disabled="cancelForm.processing"
                        :aria-busy="cancelForm.processing"
                        @click="confirmCancel"
                        ><Spinner
                            v-if="cancelForm.processing"
                            class="size-4"
                        /><span
                            :class="cancelForm.processing ? 'sr-only' : ''"
                            >{{
                                cancelForm.processing
                                    ? 'Cancelling schedule'
                                    : 'Confirm cancellation'
                            }}</span
                        ></Button
                    >
                    <Button
                        variant="outline"
                        :disabled="cancelForm.processing"
                        @click="cancelOpen = false"
                        >Keep schedule</Button
                    >
                </div>
            </DialogScrollContent>
        </Dialog>
    </div>
</template>
