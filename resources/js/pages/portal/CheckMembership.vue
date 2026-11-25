<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    ArrowRight,
    CalendarCheck,
    CalendarDays,
    CircleCheck,
    CircleHelp,
    CircleX,
    FileText,
    IdCard,
    Info,
    LockKeyhole,
    QrCode,
    Search,
    ShieldCheck,
    UserRound,
    Zap,
} from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatDisplayDate } from '@/lib/date-format';
import { jsonRequest } from '@/lib/json-request';
import { portalUrl } from '@/lib/portal-route';
import { checkAttendanceQr, checkStatus } from '@/routes/portal';

type Membership = {
    maskedName: string;
    planName: string | null;
    startsOn: string | null;
    validThrough: string | null;
    status: string;
};
const props = defineProps<{
    gymName: string;
    gymSlug: string;
    homeUrl?: string | null;
    logoUrl?: string | null;
    imageUrl?: string | null;
    qrAttendanceAvailable?: boolean;
}>();
const search = ref('');
const membership = ref<Membership | null>(null);
const error = ref('');
const busy = ref(false);
const qrImage = ref('');
const qrBusy = ref(false);
const qrError = ref('');
const resultPanel = ref<HTMLElement | null>(null);
let disposed = false;
const abort = new AbortController();
const statusClass = computed(() => {
    switch (membership.value?.status) {
        case 'Active':
            return 'border-emerald-500/30 bg-emerald-500/20 text-emerald-300';
        case 'Expiring soon':
        case 'Scheduled':
            return 'border-amber-500/30 bg-amber-500/20 text-amber-300';
        case 'Expired':
        case 'Cancelled':
            return 'border-red-500/30 bg-red-500/20 text-red-300';
        default:
            return 'border-white/15 bg-white/10 text-muted-foreground';
    }
});
function clearSearch() {
    search.value = '';
    nextTick(() => document.getElementById('membership-check-value')?.focus());
}

watch(search, () => {
    membership.value = null;
    error.value = '';
    qrImage.value = '';
    qrError.value = '';
});
async function lookup() {
    if (busy.value) return;
    busy.value = true;
    membership.value = null;
    error.value = '';
    qrImage.value = '';
    qrError.value = '';
    try {
        const result = await jsonRequest<Membership>(
            portalUrl(checkStatus(props.gymSlug)),
            { contact: search.value.trim() },
            abort.signal,
        );
        if (!disposed) {
            membership.value = result;
            await nextTick();
            resultPanel.value?.focus({ preventScroll: true });
        }
    } catch (e) {
        if (!disposed) {
            error.value =
                e instanceof Error
                    ? e.message
                    : 'Could not check membership. Try again.';
            nextTick(() =>
                document.getElementById('membership-check-value')?.focus(),
            );
        }
    } finally {
        busy.value = false;
    }
}
async function generateAttendanceQr() {
    if (qrBusy.value || !props.qrAttendanceAvailable) return;
    qrBusy.value = true;
    qrError.value = '';
    try {
        const result = await jsonRequest<{ payload: string }>(
            portalUrl(checkAttendanceQr(props.gymSlug)),
            { contact: search.value.trim() },
            abort.signal,
        );
        if (!disposed) {
            const { toDataURL } = await import('qrcode');
            qrImage.value = await toDataURL(result.payload, {
                width: 320,
                margin: 4,
                errorCorrectionLevel: 'M',
            });
        }
    } catch (e) {
        if (!disposed) {
            qrError.value =
                e instanceof Error
                    ? e.message
                    : 'Could not generate the attendance QR. Try again.';
        }
    } finally {
        qrBusy.value = false;
    }
}
onBeforeUnmount(() => {
    disposed = true;
    abort.abort();
    search.value = '';
    membership.value = null;
    qrImage.value = '';
});
</script>
<template>
    <Head title="Check membership"
        ><meta name="robots" content="noindex, nofollow"
    /></Head>
    <div class="space-y-6 short:space-y-5">
        <header class="space-y-3">
            <p
                class="text-sm font-semibold tracking-wider text-red-400 uppercase"
            >
                Membership lookup
            </p>
            <h1
                aria-label="Check your membership"
                class="text-3xl leading-tight font-bold tracking-tight sm:text-5xl lg:text-[3.25rem] short:sm:text-4xl"
            >
                Check Your <span class="text-red-500">Membership</span>
            </h1>
            <p
                class="max-w-2xl text-base leading-relaxed text-muted-foreground sm:text-lg short:sm:text-base"
            >
                Enter your phone number or email to check your membership
                details.<br class="hidden sm:block" />
                No sign-in needed.
            </p>
        </header>
        <div
            class="grid items-start gap-5 lg:grid-cols-[minmax(0,2.15fr)_minmax(17rem,1fr)] lg:gap-6"
        >
            <div class="min-w-0 space-y-5">
                <form
                    class="space-y-4 rounded-2xl border border-white/15 bg-card/85 p-4 shadow-lg backdrop-blur-sm sm:p-6 short:sm:p-5"
                    :aria-busy="busy"
                    @submit.prevent="lookup"
                >
                    <div class="grid gap-3">
                        <div
                            class="flex flex-wrap items-center justify-between gap-x-4"
                        >
                            <Label
                                for="membership-check-value"
                                class="gap-3 text-base font-semibold"
                                ><IdCard
                                    class="size-6 text-muted-foreground"
                                    aria-hidden="true"
                                />Phone number or email</Label
                            >
                            <details class="relative">
                                <summary
                                    class="flex min-h-11 cursor-pointer list-none items-center gap-2 rounded-md text-xs text-muted-foreground hover:text-foreground focus-visible:outline-2 focus-visible:outline-ring sm:text-sm"
                                >
                                    <CircleHelp
                                        class="size-4"
                                        aria-hidden="true"
                                    />Which phone number or email should I use?
                                </summary>
                                <p
                                    id="membership-check-help"
                                    class="absolute right-0 z-10 w-64 max-w-[calc(100vw-3rem)] rounded-xl border bg-card p-4 text-sm leading-relaxed shadow-xl"
                                >
                                    Use the phone number or email saved in your
                                    gym record. Ask the front desk if you need
                                    help.
                                </p>
                            </details>
                        </div>
                        <div class="relative">
                            <Input
                                id="membership-check-value"
                                v-model="search"
                                type="text"
                                inputmode="email"
                                maxlength="255"
                                placeholder="Enter your phone number or email"
                                class="h-14 rounded-xl border-primary/70 bg-white/5 pr-12 text-base font-semibold sm:h-16 sm:text-lg md:text-lg"
                                :disabled="busy"
                                autocomplete="off"
                                :spellcheck="false"
                                :aria-invalid="!!error"
                                :aria-describedby="
                                    error
                                        ? 'membership-check-help membership-check-error'
                                        : 'membership-check-help'
                                "
                                required
                            />
                            <Button
                                v-if="search"
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label="Clear phone number or email"
                                :disabled="busy"
                                class="absolute top-1/2 right-1 -translate-y-1/2 rounded-full text-muted-foreground hover:text-foreground"
                                @click="clearSearch"
                                ><CircleX class="size-5" aria-hidden="true"
                            /></Button>
                        </div>
                        <p
                            v-if="error"
                            id="membership-check-error"
                            role="alert"
                            class="text-sm text-destructive"
                        >
                            {{ error }}
                        </p>
                    </div>
                    <Button
                        type="submit"
                        :disabled="busy"
                        class="relative h-12 w-full gap-3 rounded-xl bg-linear-to-r from-primary to-primary/85 px-12 text-base font-semibold shadow-sm sm:h-14"
                        ><Spinner v-if="busy" /><Search
                            v-else
                            class="size-5"
                            aria-hidden="true" /><span>{{
                            busy ? 'Checking membership' : 'Check membership'
                        }}</span
                        ><ArrowRight
                            class="absolute right-4 size-5"
                            aria-hidden="true"
                    /></Button>
                </form>
                <section
                    v-if="membership"
                    ref="resultPanel"
                    tabindex="-1"
                    role="status"
                    aria-label="Membership details"
                    class="rounded-2xl border border-white/15 bg-card/85 p-4 shadow-lg backdrop-blur-sm outline-none focus-visible:ring-2 focus-visible:ring-white/40 sm:p-6 short:sm:p-5"
                >
                    <h2 class="flex items-center gap-3 text-lg font-semibold">
                        <span
                            class="flex size-10 items-center justify-center rounded-xl border border-primary/20 bg-primary/15 text-red-400"
                            ><FileText
                                class="size-5"
                                aria-hidden="true" /></span
                        >Membership details
                    </h2>
                    <dl class="mt-4 border-b border-white/10 pb-4">
                        <div class="flex min-w-0 gap-3 sm:px-2">
                            <UserRound
                                class="mt-1 size-5 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <div class="min-w-0">
                                <dt class="text-sm text-muted-foreground">
                                    Member name
                                </dt>
                                <dd
                                    class="mt-1 font-semibold break-words sm:text-lg"
                                >
                                    {{ membership.maskedName }}
                                </dd>
                            </div>
                        </div>
                    </dl>
                    <dl class="my-3 grid sm:grid-cols-2">
                        <div
                            v-for="field in [
                                {
                                    label: 'Plan',
                                    value:
                                        membership.planName ||
                                        'No plan assigned',
                                    icon: IdCard,
                                },
                                {
                                    label: 'Start date',
                                    value: membership.startsOn
                                        ? formatDisplayDate(membership.startsOn)
                                        : 'Not assigned',
                                    icon: CalendarDays,
                                },
                                {
                                    label: 'Valid through',
                                    value: membership.validThrough
                                        ? formatDisplayDate(
                                              membership.validThrough,
                                          )
                                        : 'Not assigned',
                                    icon: CalendarCheck,
                                },
                                {
                                    label: 'Status',
                                    value: membership.status,
                                    icon: CircleCheck,
                                },
                            ]"
                            :key="field.label"
                            class="flex min-w-0 gap-3 border-white/10 py-4 not-last:border-b sm:px-2 sm:odd:border-r sm:odd:pr-4 sm:even:pl-5 sm:[&:nth-child(3)]:border-b-0"
                        >
                            <component
                                :is="field.icon"
                                class="mt-1 size-5 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <div class="min-w-0">
                                <dt class="text-sm text-muted-foreground">
                                    {{ field.label }}
                                </dt>
                                <dd
                                    class="mt-1 font-semibold break-words sm:text-lg"
                                >
                                    <Badge
                                        v-if="field.label === 'Status'"
                                        variant="outline"
                                        :class="statusClass"
                                        class="rounded-md px-3 py-1 text-sm"
                                        >{{ field.value }}</Badge
                                    ><template v-else>{{
                                        field.value
                                    }}</template>
                                </dd>
                            </div>
                        </div>
                    </dl>
                    <div
                        class="flex gap-3 rounded-xl border border-white/10 bg-white/5 p-3 text-sm"
                    >
                        <Info
                            class="mt-0.5 size-5 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <div>
                            <p class="font-medium">
                                Have questions about your membership?
                            </p>
                            <p class="mt-1 text-muted-foreground">
                                Contact the front desk for renewals or
                                inquiries. Dates use your gym's timezone.
                            </p>
                        </div>
                    </div>
                    <div
                        v-if="props.qrAttendanceAvailable"
                        class="mt-4 rounded-xl border border-primary/20 bg-primary/5 p-4"
                    >
                        <div class="flex items-start gap-3">
                            <QrCode
                                class="mt-0.5 size-5 shrink-0 text-red-400"
                                aria-hidden="true"
                            />
                            <div class="min-w-0">
                                <h3 class="font-semibold">Attendance QR</h3>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Generate a QR for staff to scan at check-in.
                                    Keep it private.
                                </p>
                            </div>
                        </div>
                        <Button
                            v-if="!qrImage"
                            class="mt-4"
                            :disabled="qrBusy"
                            @click="generateAttendanceQr"
                        >
                            <Spinner v-if="qrBusy" />
                            <QrCode v-else class="size-4" aria-hidden="true" />
                            {{
                                qrBusy
                                    ? 'Generating QR'
                                    : 'Generate attendance QR'
                            }}
                        </Button>
                        <p
                            v-if="qrError"
                            role="alert"
                            class="mt-3 text-sm text-destructive"
                        >
                            {{ qrError }}
                        </p>
                        <template v-if="qrImage">
                            <img
                                :src="qrImage"
                                alt="Attendance QR"
                                class="mt-4 w-full max-w-80 rounded-lg bg-white p-3"
                                width="320"
                                height="320"
                            />
                            <Button as-child variant="outline" class="mt-3">
                                <a :href="qrImage" download="attendance-qr.png"
                                    >Download attendance QR</a
                                >
                            </Button>
                        </template>
                    </div>
                </section>
            </div>
            <aside
                aria-label="About membership lookup"
                class="space-y-6 rounded-2xl border border-white/15 bg-card/85 p-5 shadow-lg backdrop-blur-sm sm:p-6 short:space-y-5"
            >
                <div
                    v-for="item in [
                        {
                            title: 'Quick lookup',
                            description:
                                'Use the phone number or email saved with your membership.',
                            icon: Zap,
                        },
                        {
                            title: 'No sign-in needed',
                            description:
                                'Check your membership without signing in.',
                            icon: LockKeyhole,
                        },
                        {
                            title: 'Membership details only',
                            description:
                                'View your masked name, plan, dates and status. Contact and payment details stay private.',
                            icon: ShieldCheck,
                        },
                    ]"
                    :key="item.title"
                    class="flex items-start gap-4"
                >
                    <span
                        class="flex size-12 shrink-0 items-center justify-center rounded-xl border border-white/10 bg-white/5 text-red-400"
                        ><component
                            :is="item.icon"
                            class="size-6"
                            aria-hidden="true"
                    /></span>
                    <div class="min-w-0 pt-1">
                        <h2 class="font-semibold">{{ item.title }}</h2>
                        <p
                            class="mt-1 text-sm leading-relaxed text-muted-foreground"
                        >
                            {{ item.description }}
                        </p>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</template>
