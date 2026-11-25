<script setup lang="ts">
import { formatDisplayDate } from '@/lib/date-format';
import { Form, Link } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import { decimalUnits, discountSavings } from '@/lib/payment-discount';
import type { PaymentFormData } from '@/types/member-profile';
import { Spinner } from '@/components/ui/spinner';
import PaymentController from '@/actions/App/Http/Controllers/Dashboard/PaymentController';
import InputError from '@/components/InputError.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import { memberTabUrl } from '@/lib/member-tab-url';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<PaymentFormData & { embedded?: boolean }>();
const eligible = computed(() =>
    [
        ...props.subscriptions.data.map((item) => ({
            ...item,
            key: 'subscription-' + item.id,
            paymentField: 'member_subscription_id',
            kindLabel: 'Gym subscription',
        })),
        ...props.purchases.data.map((item) => ({
            ...item,
            key: 'purchase-' + item.id,
            paymentField: 'member_purchase_id',
            kindLabel:
                item.kind === 'package'
                    ? 'Training package'
                    : 'Membership plan',
        })),
    ].filter((item) => item.balanceCentavos > 0 && item.canRecord),
);
const selectedId = ref('');
const selected = computed(() =>
    eligible.value.find((item) => item.key === selectedId.value),
);
const amount = ref('');
const percent = ref('0');
const calculation = computed(() => {
    const cents = decimalUnits(amount.value);
    const points = decimalUnits(percent.value);
    if (!selected.value || cents === null || points === null || points > 10000)
        return null;
    const base = cents;
    const savings = discountSavings(base, points);
    const received = cents - savings;
    return {
        base,
        savings,
        received,
        remaining: selected.value.balanceCentavos - received - savings,
    };
});
watch(eligible, (items) => {
    if (!items.some((item) => item.key === selectedId.value))
        selectedId.value = '';
});
watch(selectedId, () => {
    amount.value = selected.value
        ? (selected.value.balanceCentavos / 100).toFixed(2)
        : '';
    percent.value = '0';
});
const requestKey = ref(crypto.randomUUID());
const hadError = ref(false);
function saved() {
    requestKey.value = crypto.randomUUID();
    selectedId.value = '';
    amount.value = '';
    percent.value = '0';
}
async function focusError() {
    if (!hadError.value) return;
    hadError.value = false;
    await nextTick();
    document
        .querySelector<HTMLElement>(
            '#main-content [aria-invalid="true"], #payment-error',
        )
        ?.focus();
}
const currency = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
});
</script>
<template>
    <div class="space-y-5">
        <p v-if="!canRecord" role="status">
            This branch is read-only. Activate it before recording payments.
        </p>
        <p v-else-if="!eligible.length">
            No payable balances on this page. Add a purchase or check another
            page.
        </p>
        <Form
            v-else
            v-bind="PaymentController.store.form(member.id)"
            v-slot="{ errors, processing }"
            class="space-y-4"
            reset-on-success
            @success="saved"
            @error="hadError = true"
            @finish="focusError"
        >
            <p class="text-sm text-muted-foreground">
                Choose an existing purchased subscription, membership plan or
                training package. Amounts use its saved price and remaining
                balance. To renew a membership, use the renewal checkout.
            </p>
            <input
                v-if="embedded"
                type="hidden"
                name="return_to_profile"
                value="1"
            /><input type="hidden" name="request_key" :value="requestKey" />
            <input
                v-if="selected"
                type="hidden"
                :name="selected.paymentField"
                :value="selected.id"
            />
            <fieldset
                :disabled="processing"
                :aria-busy="processing"
                class="grid gap-4 sm:grid-cols-2"
            >
                <div class="space-y-2">
                    <Label for="payment-subscription">Purchase</Label>
                    <NativeSelect
                        id="payment-subscription"
                        v-model="selectedId"
                        required
                        :aria-invalid="
                            !!(
                                errors.member_subscription_id ||
                                errors.member_purchase_id
                            )
                        "
                        :aria-describedby="
                            errors.member_subscription_id ||
                            errors.member_purchase_id
                                ? 'payment-subscription-error'
                                : undefined
                        "
                    >
                        <option value="" disabled>Select a purchase</option>
                        <option
                            v-for="subscription in eligible"
                            :key="subscription.key"
                            :value="subscription.key"
                        >
                            {{ subscription.kindLabel }}:
                            {{ subscription.planName }} /
                            {{ formatDisplayDate(subscription.startsOn) }} /
                            {{
                                currency.format(
                                    subscription.balanceCentavos / 100,
                                )
                            }}
                            remaining
                        </option>
                    </NativeSelect>
                    <InputError
                        id="payment-subscription-error"
                        :message="
                            errors.member_subscription_id ||
                            errors.member_purchase_id
                        "
                    />
                </div>
                <div class="space-y-2">
                    <Label for="payment-amount"
                        >Amount before discount (PHP)</Label
                    >
                    <Input
                        id="payment-amount"
                        name="amount_php"
                        v-model="amount"
                        inputmode="decimal"
                        type="text"
                        placeholder="1500.50"
                        required
                        maxlength="10"
                        :aria-invalid="!!errors.amount_php"
                        :aria-describedby="
                            errors.amount_php
                                ? 'payment-amount-error'
                                : undefined
                        "
                    />
                    <InputError
                        id="payment-amount-error"
                        :message="errors.amount_php"
                    />
                </div>
                <div class="space-y-2">
                    <Label for="payment-discount-percent"
                        >Discount / sale (%)</Label
                    >
                    <Input
                        id="payment-discount-percent"
                        name="discount_percent"
                        v-model="percent"
                        inputmode="decimal"
                        maxlength="6"
                        :aria-invalid="!!errors.discount_percent"
                        aria-describedby="payment-discount-help payment-discount-error"
                    />
                    <p
                        id="payment-discount-help"
                        class="text-sm text-muted-foreground"
                    >
                        Optional additional sale. Applies to this payment only;
                        purchase discounts are already included in the saved
                        balance. Enter 0 to 100, with up to two decimal places.
                    </p>
                    <InputError
                        id="payment-discount-error"
                        :message="errors.discount_percent"
                    />
                </div>

                <div
                    v-if="calculation"
                    class="rounded-lg border bg-muted/40 p-4 text-sm sm:col-span-2"
                >
                    <p class="mb-3 text-muted-foreground">
                        {{ selected?.planName }} · {{ selected?.duration }}
                    </p>
                    <dl class="grid grid-cols-2 gap-2">
                        <dt>Amount before discount</dt>
                        <dd class="text-right">
                            {{ currency.format(calculation.base / 100) }}
                        </dd>
                        <dt>Additional savings ({{ percent }}%)</dt>
                        <dd class="text-right">
                            {{ currency.format(calculation.savings / 100) }}
                        </dd>
                        <dt class="font-semibold">Payment received</dt>
                        <dd class="text-right font-semibold">
                            {{ currency.format(calculation.received / 100) }}
                        </dd>
                        <dt>Balance after payment</dt>
                        <dd class="text-right">
                            {{ currency.format(calculation.remaining / 100) }}
                        </dd>
                    </dl>
                </div>
                <div class="space-y-2">
                    <Label for="payment-method">Payment method</Label
                    ><NativeSelect id="payment-method" name="method" required
                        ><option value="cash">Cash</option>
                        <option value="gcash">GCash</option>
                        <option value="maya">Maya</option>
                        <option value="bank-transfer">
                            Bank transfer
                        </option></NativeSelect
                    ><InputError :message="errors.method" />
                </div>
                <div class="space-y-2">
                    <Label for="payment-reference">Reference (optional)</Label
                    ><Input
                        id="payment-reference"
                        name="reference"
                        maxlength="100"
                    /><InputError :message="errors.reference" />
                </div>
                <div class="sm:col-span-2">
                    <InputError
                        id="payment-error"
                        :message="errors.request_key"
                        tabindex="-1"
                    /><Button
                        type="submit"
                        :disabled="
                            processing ||
                            !calculation ||
                            calculation.remaining < 0
                        "
                        ><Spinner v-if="processing" />Apply payment</Button
                    >
                </div>
            </fieldset></Form
        >
        <nav class="flex flex-wrap gap-3" aria-label="Payment purchase pages">
            <Link
                v-if="subscriptions.prev_page_url"
                :href="
                    embedded
                        ? memberTabUrl(subscriptions.prev_page_url, 'payment')
                        : subscriptions.prev_page_url
                "
                class="min-h-11 py-3 text-sm underline"
                preserve-scroll
                >Newer subscriptions</Link
            >
            <Link
                v-if="subscriptions.next_page_url"
                :href="
                    embedded
                        ? memberTabUrl(subscriptions.next_page_url, 'payment')
                        : subscriptions.next_page_url
                "
                class="min-h-11 py-3 text-sm underline"
                preserve-scroll
                >Older subscriptions</Link
            >
            <Link
                v-if="purchases.prev_page_url"
                :href="
                    embedded
                        ? memberTabUrl(purchases.prev_page_url, 'payment')
                        : purchases.prev_page_url
                "
                class="min-h-11 py-3 text-sm underline"
                preserve-scroll
                >Newer benefits/packages</Link
            >
            <Link
                v-if="purchases.next_page_url"
                :href="
                    embedded
                        ? memberTabUrl(purchases.next_page_url, 'payment')
                        : purchases.next_page_url
                "
                class="min-h-11 py-3 text-sm underline"
                preserve-scroll
                >Older benefits/packages</Link
            >
        </nav>
    </div>
</template>
