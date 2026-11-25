<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { store as register } from '@/routes/members';
import { store as purchase } from '@/routes/purchases';
import { quote } from '@/routes/pricing';
import Controller from '@/actions/App/Http/Controllers/Dashboard/MembershipPlanController';
import NativeSelect from '@/components/NativeSelect.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { jsonRequest } from '@/lib/json-request';
import type {
    PricingCatalogData,
    CheckoutSummary,
    Offering,
} from '@/types/pricing';
const props = defineProps<
    PricingCatalogData & {
        member?: {
            id: number;
            name: string;
            pricing_discount_id: number | null;
            eligibility_from: string | null;
            eligibility_through: string | null;
            reservedPeriods?: {
                kind: 'subscription' | 'benefit';
                planName: string;
                startsOn: string;
                endsOn: string | null;
            }[];
        } | null;
        eligibilityVerified?: boolean;
        canSave: boolean;
        renewalAvailable?: boolean;
    }
>();
const form = useForm({
    name: '',
    mobile: '',
    email: '',
    pricing_checkout: true,
    membership_plan_id: '' as number | '',
    benefit_plan_id: '' as number | '',
    package_ids: [] as number[],
    starts_on: props.today,
    benefit_starts_on: props.today,
    pricing_discount_id:
        props.member?.pricing_discount_id ?? ('' as number | ''),
    eligibility_verified: props.eligibilityVerified ?? false,
    eligibility_from: props.member?.eligibility_from ?? '',
    eligibility_through: props.member?.eligibility_through ?? '',
    operation: props.renewalAvailable ? 'renew' : 'assign',
    request_key: crypto.randomUUID() as string,
    paid_php: '',
    method: 'cash',
    reference: '',
});
const selectedDiscount = computed(() =>
    props.discounts.find((d) => d.id === form.pricing_discount_id),
);
const reservedPeriods = computed(() => props.member?.reservedPeriods ?? []);
function addDuration(
    start: string,
    amount: number,
    unit: Offering['duration_unit'],
) {
    const [year, month, day] = start.split('-').map(Number);
    const date = new Date(Date.UTC(year, month - 1, day));
    if (unit === 'months') {
        const monthIndex = date.getUTCMonth() + amount;
        const targetYear = date.getUTCFullYear() + Math.floor(monthIndex / 12);
        const targetMonth = ((monthIndex % 12) + 12) % 12;
        const lastDay = new Date(
            Date.UTC(targetYear, targetMonth + 1, 0),
        ).getUTCDate();
        date.setUTCFullYear(targetYear, targetMonth, Math.min(day, lastDay));
    } else {
        date.setUTCDate(date.getUTCDate() + amount);
    }

    return date.toISOString().slice(0, 10);
}
function overlapsReserved(
    kind: 'subscription' | 'benefit',
    offering: Offering,
    start: string,
) {
    if (!props.member || !start) return false;
    const end = addDuration(
        start,
        offering.duration_amount,
        offering.duration_unit,
    );

    return reservedPeriods.value.some(
        (period) =>
            period.kind === kind &&
            period.startsOn < end &&
            (!period.endsOn || period.endsOn > start),
    );
}
function subscriptionBlocked(offering: Offering) {
    return (
        form.operation === 'assign' &&
        overlapsReserved('subscription', offering, form.starts_on)
    );
}
function benefitBlocked(offering: Offering) {
    return overlapsReserved('benefit', offering, form.benefit_starts_on);
}
const shortDate = new Intl.DateTimeFormat('en-PH', {
    month: 'short',
    day: 'numeric',
});
function warningFor(
    kind: 'subscription' | 'benefit',
    start: string,
    blocked: boolean,
) {
    if (!props.member || !blocked) return '';
    const offering = props.offerings.find(
        (item) =>
            item.kind === kind &&
            (kind === 'subscription'
                ? subscriptionBlocked(item)
                : benefitBlocked(item)),
    );
    if (!offering) return '';
    const end = addDuration(
        start,
        offering.duration_amount,
        offering.duration_unit,
    );
    const period = reservedPeriods.value.find(
        (item) =>
            item.kind === kind &&
            item.startsOn < end &&
            (!item.endsOn || item.endsOn > start),
    );
    if (!period) return '';
    const through = period.endsOn
        ? shortDate.format(
              new Date(`${addDuration(period.endsOn, -1, 'days')}T00:00:00Z`),
          )
        : 'no end date';

    return `${kind === 'subscription' ? 'Active subscription' : 'Active membership plan'}: ${shortDate.format(new Date(`${period.startsOn}T00:00:00Z`))} – ${through}. Choose a start date after ${through}.`;
}
const hasReservedConflict = computed(() => {
    const subscription = props.offerings.find(
        (offering) => offering.id === form.membership_plan_id,
    );
    const benefit = props.offerings.find(
        (offering) => offering.id === form.benefit_plan_id,
    );

    return (
        (subscription !== undefined && subscriptionBlocked(subscription)) ||
        (benefit !== undefined && benefitBlocked(benefit))
    );
});
const subscriptionWarning = computed(() =>
    warningFor(
        'subscription',
        form.starts_on,
        form.operation === 'assign' &&
            props.offerings.some((offering) => subscriptionBlocked(offering)),
    ),
);
const benefitWarning = computed(() =>
    warningFor(
        'benefit',
        form.benefit_starts_on,
        props.offerings.some((offering) => benefitBlocked(offering)),
    ),
);
const summary = ref<CheckoutSummary | null>(null);
const quoteError = ref('');
const quoting = ref(false);
const errorsRef = ref<HTMLElement | null>(null);
let timer: ReturnType<typeof setTimeout> | undefined;
let abort: AbortController | undefined;
const quoteBody = computed(() => ({
    membership_plan_id: form.membership_plan_id || null,
    benefit_plan_id: form.benefit_plan_id || null,
    package_ids: form.package_ids,
    starts_on: form.starts_on,
    benefit_starts_on: form.benefit_starts_on,
    pricing_discount_id: form.pricing_discount_id || null,
    eligibility_verified: form.eligibility_verified,
    eligibility_from: form.eligibility_from || null,
    eligibility_through: form.eligibility_through || null,
    operation: form.operation,
    request_key: form.request_key,
    member_id: props.member?.id ?? null,
}));
watch(
    [() => form.starts_on, () => form.benefit_starts_on, () => form.operation],
    () => {
        const subscription = props.offerings.find(
            (offering) => offering.id === form.membership_plan_id,
        );
        const benefit = props.offerings.find(
            (offering) => offering.id === form.benefit_plan_id,
        );
        if (subscription && subscriptionBlocked(subscription)) {
            form.membership_plan_id = '';
        }
        if (benefit && benefitBlocked(benefit)) {
            form.benefit_plan_id = '';
        }
    },
);
watch(
    quoteBody,
    () => {
        abort?.abort();
        clearTimeout(timer);
        summary.value = null;
        quoteError.value = '';
        if (
            props.pricingSettings.require_purchase &&
            !form.membership_plan_id &&
            !form.benefit_plan_id &&
            !form.package_ids.length
        ) {
            quoting.value = false;
            return;
        }
        quoting.value = true;
        timer = setTimeout(loadQuote, 250);
    },
    { deep: true, immediate: true },
);
async function loadQuote() {
    abort = new AbortController();
    const signal = abort.signal;
    try {
        summary.value = await jsonRequest<CheckoutSummary>(
            quote().url,
            quoteBody.value,
            signal,
        );
    } catch (error) {
        if (!signal.aborted)
            quoteError.value =
                error instanceof Error
                    ? error.message
                    : 'Unable to calculate the price.';
    } finally {
        if (!signal.aborted) quoting.value = false;
    }
}
onBeforeUnmount(() => {
    abort?.abort();
    clearTimeout(timer);
});
const currency = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
});
const price = (c: number) => currency.format(c / 100);
const page = usePage();
const paymentsEnabled = computed(() =>
    (page.props.features as string[]).includes('payments'),
);
const paymentChoice = ref<'paid' | 'unpaid' | null>(null);
const canPayInFull = computed(
    () =>
        !!summary.value &&
        summary.value.total_centavos > 0 &&
        summary.value.total_centavos <= 999_999_999,
);
const canSubmit = computed(
    () =>
        props.canSave &&
        !!summary.value &&
        !hasReservedConflict.value &&
        !form.processing &&
        (!paymentsEnabled.value ||
            paymentChoice.value === 'unpaid' ||
            (paymentChoice.value === 'paid' && canPayInFull.value)),
);
function save() {
    if (!canSubmit.value) return;
    form.paid_php =
        paymentChoice.value === 'paid'
            ? (summary.value!.total_centavos / 100).toFixed(2)
            : '';
    if (paymentChoice.value !== 'paid') form.reference = '';
    const options = {
        preserveScroll: true,
        onError: () => {
            nextTick(() => errorsRef.value?.focus());
        },
        onSuccess: () => {
            form.request_key = crypto.randomUUID();
            paymentChoice.value = null;
            if (props.member) {
                form.reset(
                    'membership_plan_id',
                    'benefit_plan_id',
                    'package_ids',
                    'paid_php',
                    'reference',
                );
            }
        },
    };
    if (props.member) form.post(purchase(props.member.id).url, options);
    else form.post(register().url, options);
}
</script>
<template>
    <form
        class="grid items-start gap-4 @3xl:grid-cols-[minmax(0,1fr)_20rem]"
        @submit.prevent="save"
    >
        <div class="space-y-4">
            <section
                v-if="!member"
                class="space-y-4 rounded-xl border bg-card p-4 sm:p-5"
            >
                <h2 class="font-semibold">Member information</h2>
                <label class="block space-y-1 text-sm"
                    >Full name<Input
                        v-model="form.name"
                        required
                        maxlength="255"
                        autocomplete="name"
                        :aria-invalid="!!form.errors.name"
                /></label>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="space-y-1 text-sm"
                        >Mobile number (optional)<Input
                            v-model="form.mobile"
                            type="tel"
                            maxlength="20"
                            autocomplete="tel" /></label
                    ><label class="space-y-1 text-sm"
                        >Email (optional)<Input
                            v-model="form.email"
                            type="email"
                            maxlength="255"
                            autocomplete="email"
                    /></label>
                </div>
                <p class="text-xs text-muted-foreground">
                    This creates a gym member profile. A member sign-in account
                    is optional and managed separately.
                </p>
            </section>
            <section class="space-y-4 rounded-xl border bg-card p-4 sm:p-5">
                <h2 class="font-semibold">Gym subscription</h2>
                <p class="text-xs text-muted-foreground">
                    Choose gym access, a separate membership plan, or training
                    packages.
                </p>
                <label class="block space-y-1 text-sm"
                    >Subscription<NativeSelect
                        aria-label="Subscription"
                        v-model="form.membership_plan_id"
                        ><option value="">No gym subscription</option>
                        <option
                            v-for="p in offerings.filter(
                                (p) => p.kind === 'subscription',
                            )"
                            :key="p.id"
                            :value="p.id"
                            :disabled="subscriptionBlocked(p)"
                        >
                            {{ p.name }} · {{ price(p.price_centavos) }} /
                            {{ p.duration_amount }} {{ p.duration_unit }}
                            {{
                                subscriptionBlocked(p)
                                    ? ' · Overlaps reserved period'
                                    : ''
                            }}
                        </option></NativeSelect
                    ></label
                >
                <div class="grid gap-4 sm:grid-cols-2">
                    <label
                        v-if="
                            member &&
                            offerings.some((p) => p.kind === 'subscription')
                        "
                        class="space-y-1 text-sm"
                        >Assignment<NativeSelect
                            aria-label="Assignment"
                            v-model="form.operation"
                            ><option value="assign">
                                Start on selected date
                            </option>
                            <option value="renew">
                                Renew after reserved periods
                            </option></NativeSelect
                        ></label
                    ><label
                        v-if="form.operation !== 'renew'"
                        class="space-y-1 text-sm"
                        >Start date<Input
                            v-model="form.starts_on"
                            type="date"
                            required
                    /></label>
                </div>
                <p
                    v-if="subscriptionWarning"
                    role="alert"
                    class="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-200"
                >
                    {{ subscriptionWarning }}
                </p>
            </section>
            <section class="space-y-4 rounded-xl border bg-card p-4 sm:p-5">
                <h2 class="font-semibold">Membership plan (optional)</h2>
                <label class="block space-y-1 text-sm"
                    >Membership plan<NativeSelect
                        aria-label="Membership plan"
                        v-model="form.benefit_plan_id"
                        ><option value="">No paid membership plan</option>
                        <option
                            v-for="p in offerings.filter(
                                (p) => p.kind === 'benefit',
                            )"
                            :key="p.id"
                            :value="p.id"
                            :disabled="benefitBlocked(p)"
                        >
                            {{ p.name }} · {{ price(p.price_centavos) }}
                            {{
                                benefitBlocked(p)
                                    ? ' · Overlaps reserved period'
                                    : ''
                            }}
                        </option></NativeSelect
                    ></label
                ><label
                    v-if="form.benefit_plan_id"
                    class="block space-y-1 text-sm"
                    >Membership start date<Input
                        v-model="form.benefit_starts_on"
                        type="date"
                        required
                /></label>
                <p class="text-xs text-muted-foreground">
                    The benefit fee is separate from gym access. Its discount
                    applies only during its valid period and never to its own
                    fee.
                </p>
                <p
                    v-if="benefitWarning"
                    role="alert"
                    class="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-200"
                >
                    {{ benefitWarning }}
                </p>
            </section>
            <section class="space-y-4 rounded-xl border bg-card p-4 sm:p-5">
                <h2 class="font-semibold">Discount eligibility</h2>
                <label class="block space-y-1 text-sm"
                    >Member type<NativeSelect
                        aria-label="Member type"
                        v-model="form.pricing_discount_id"
                        ><option value="">
                            Regular · no category discount
                        </option>
                        <option
                            v-for="d in discounts"
                            :key="d.id"
                            :value="d.id"
                        >
                            {{ d.name }}
                        </option></NativeSelect
                    ></label
                ><template v-if="selectedDiscount?.requires_verification"
                    ><label class="flex items-start gap-2 text-sm"
                        ><input
                            v-model="form.eligibility_verified"
                            type="checkbox"
                            class="mt-1"
                        />I verified this member’s eligibility. Supporting
                        documents stay offline.</label
                    >
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="space-y-1 text-sm"
                            >Eligible from (optional)<Input
                                v-model="form.eligibility_from"
                                type="date" /></label
                        ><label class="space-y-1 text-sm"
                            >Eligible through (optional)<Input
                                v-model="form.eligibility_through"
                                type="date"
                        /></label></div
                ></template>
                <p
                    v-if="pricingSettings.promotion_stacking === 'none'"
                    class="text-xs text-muted-foreground"
                >
                    The best eligible promotion applies. Statutory discounts are
                    compared separately.
                </p>
            </section>
            <section class="space-y-3 rounded-xl border bg-card p-4 sm:p-5">
                <h2 class="font-semibold">Training packages (optional)</h2>
                <label
                    v-for="p in offerings.filter((p) => p.kind === 'package')"
                    :key="p.id"
                    class="flex items-start justify-between gap-3 rounded-lg border p-3 text-sm"
                    ><span class="flex gap-2"
                        ><input
                            v-model="form.package_ids"
                            type="checkbox"
                            :value="p.id"
                            class="mt-1"
                        /><span
                            ><span class="font-medium">{{ p.name }}</span
                            ><span class="block text-xs text-muted-foreground"
                                >{{ p.configuration?.sessions }}
                                sessions ·
                                {{
                                    p.configuration?.duration_mode ===
                                        'sessions' ||
                                    p.configuration?.unlimited_validity
                                        ? 'Per session'
                                        : p.duration_amount +
                                          ' ' +
                                          p.duration_unit
                                }}</span
                            ></span
                        ></span
                    ><span class="shrink-0 font-semibold">{{
                        price(p.price_centavos)
                    }}</span></label
                >
                <p
                    v-if="!offerings.some((p) => p.kind === 'package')"
                    class="text-sm text-muted-foreground"
                >
                    No training packages are available.
                </p>
            </section>
        </div>
        <aside
            class="space-y-4 rounded-xl border bg-card p-4 sm:p-5 @3xl:sticky @3xl:top-4"
        >
            <h2 class="font-semibold">Checkout summary</h2>
            <p
                v-if="quoting"
                role="status"
                class="text-sm text-muted-foreground"
            >
                Calculating…
            </p>
            <p
                v-if="quoteError"
                role="status"
                class="text-sm text-muted-foreground"
            >
                {{ quoteError }}
            </p>
            <p
                v-if="!summary && !quoting && !quoteError"
                class="text-sm text-muted-foreground"
            >
                Choose a subscription, membership plan or training package.
            </p>
            <div v-if="summary" class="space-y-3" aria-live="polite">
                <div
                    v-for="line in summary.lines"
                    :key="line.plan_id"
                    class="space-y-1 border-b pb-3 text-sm"
                >
                    <div class="flex justify-between gap-2">
                        <span>{{ line.name }}</span
                        ><span class="shrink-0">{{
                            price(line.original_centavos)
                        }}</span>
                    </div>
                    <template v-for="(d, i) in line.discounts" :key="i"
                        ><p
                            class="flex justify-between gap-2 text-xs text-emerald-700 dark:text-emerald-400"
                        >
                            <span>{{ d.reason }}</span
                            ><span class="shrink-0"
                                >−{{ price(d.savings_centavos) }}</span
                            >
                        </p>
                        <p
                            v-if="d.vat_exemption_centavos"
                            class="flex justify-between text-xs text-muted-foreground"
                        >
                            <span>VAT exemption</span
                            ><span>−{{ price(d.vat_exemption_centavos) }}</span>
                        </p></template
                    >
                    <p class="text-right font-medium">
                        {{ price(line.total_centavos) }}
                    </p>
                </div>
                <p class="flex justify-between font-semibold">
                    <span>Total</span
                    ><span>{{ price(summary.total_centavos) }}</span>
                </p>
            </div>
            <div v-if="paymentsEnabled" class="space-y-3 text-sm">
                <p>Payment status</p>
                <div class="grid grid-cols-2 gap-2">
                    <Button
                        type="button"
                        :variant="
                            paymentChoice === 'paid' ? 'default' : 'outline'
                        "
                        :aria-pressed="paymentChoice === 'paid'"
                        :disabled="!canPayInFull"
                        @click="paymentChoice = 'paid'"
                        >Paid</Button
                    >
                    <Button
                        type="button"
                        :variant="
                            paymentChoice === 'unpaid' ? 'default' : 'outline'
                        "
                        :aria-pressed="paymentChoice === 'unpaid'"
                        :disabled="!summary"
                        @click="paymentChoice = 'unpaid'"
                        >Unpaid</Button
                    >
                </div>
                <p
                    v-if="paymentChoice === 'paid'"
                    class="text-xs text-muted-foreground"
                >
                    Record the full
                    {{ summary ? price(summary.total_centavos) : 'total' }} now.
                </p>
                <p
                    v-else-if="paymentChoice === 'unpaid'"
                    class="text-xs text-muted-foreground"
                >
                    Save the purchase with its full balance unpaid.
                </p>
                <p v-else-if="summary" class="text-xs text-muted-foreground">
                    Choose Paid or Unpaid to enable Save.
                </p>
                <label
                    v-if="paymentChoice === 'paid'"
                    class="block space-y-1 text-sm"
                >
                    Payment method
                    <NativeSelect
                        aria-label="Payment method"
                        v-model="form.method"
                    >
                        <option value="cash">Cash</option>
                        <option value="gcash">GCash</option>
                        <option value="maya">Maya</option>
                        <option value="bank-transfer">Bank transfer</option>
                    </NativeSelect>
                </label>
                <label
                    v-if="paymentChoice === 'paid'"
                    class="block space-y-1 text-sm"
                >
                    Reference (optional)
                    <Input v-model="form.reference" maxlength="100" />
                </label>
            </div>
            <p v-if="!canSave" class="text-xs text-destructive">
                {{
                    member
                        ? "This member's home branch is inactive."
                        : 'No active branch is available.'
                }}
                Purchases cannot be saved.
            </p>
            <p
                v-else-if="
                    summary &&
                    paymentChoice === 'paid' &&
                    summary.total_centavos <= 0
                "
                class="text-xs text-muted-foreground"
            >
                No payment is due. Choose Unpaid to save.
            </p>
            <p
                v-else-if="summary && paymentChoice === 'paid' && !canPayInFull"
                class="text-xs text-muted-foreground"
            >
                This total exceeds one payment's limit. Choose Unpaid and record
                payments separately.
            </p>
            <div
                v-if="Object.keys(form.errors).length"
                ref="errorsRef"
                role="alert"
                tabindex="-1"
            >
                <InputError
                    v-for="(error, key) in form.errors"
                    :key="key"
                    :message="error"
                />
            </div>
            <Button type="submit" class="w-full" :disabled="!canSubmit">{{
                member ? 'Save purchases' : 'Add member'
            }}</Button
            ><Button
                v-if="!offerings.length"
                variant="outline"
                as-child
                class="w-full"
                ><Link :href="Controller.index()"
                    >Configure Plans & Pricing</Link
                ></Button
            >
        </aside>
    </form>
</template>
