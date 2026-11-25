<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import MemberCheckout from '@/components/members/MemberCheckout.vue';
import RecordList from '@/components/RecordList.vue';
import InputError from '@/components/InputError.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import { index as appointments } from '@/routes/appointments';
import { change } from '@/routes/purchases';
import { show } from '@/routes/members';
import { store as recordPayment } from '@/routes/payments';
import type { PricingCatalogData } from '@/types/pricing';
type Purchase = {
    id: number;
    kind: string;
    plan_name: string;
    price_centavos: number;
    original_price_centavos: number;
    remaining: number;
    sessions: number;
    used_sessions: number;
    session_adjustment: number;
    snapshot: {
        discounts: {
            reason: string;
            basis_centavos: number;
            savings_centavos: number;
            vat_exemption_centavos?: number;
        }[];
    };
    balance: number;
    status: string;
    startsOn: string;
    validThrough: string;
    createdAt: string;
    createdBy: string;
};
const props = defineProps<
    PricingCatalogData & {
        member: {
            id: number;
            name: string;
            pricing_discount_id: number | null;
            eligibility_from: string | null;
            eligibility_through: string | null;
        };
        eligibilityVerified: boolean;
        canSave: boolean;
        renewalAvailable: boolean;
        purchases: {
            data: Purchase[];
            prev_page_url: string | null;
            next_page_url: string | null;
        };
        canManage: boolean;
        redemptions: {
            id: number;
            purchaseId: number;
            appointmentId: number;
            date: string | null;
            createdAt: string;
            createdBy: string;
        }[];
    }
>();
const currency = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
});
const price = (c: number) => currency.format(c / 100);
const selected = ref<Purchase | null>(null);
const management = useForm({
    action: 'cancel',
    adjustment: 0,
    expected_adjustment: 0,
    expected_used: 0,
    reason: '',
});
const payment = useForm({
    return_to_purchases: true,
    member_purchase_id: 0,
    amount_php: '',
    method: 'cash',
    reference: '',
    request_key: crypto.randomUUID() as string,
});
const paymentsEnabled = (usePage().props.features as string[]).includes(
    'payments',
);
const dialog = ref(false);
const mode = ref('payment');
function open(row: Purchase, action: string) {
    selected.value = row;
    mode.value = action;
    management.reset();
    management.expected_adjustment = row.session_adjustment;
    management.expected_used = row.used_sessions;
    management.clearErrors();
    payment.reset();
    payment.clearErrors();
    payment.member_purchase_id = row.id;
    payment.amount_php = (row.balance / 100).toFixed(2);
    payment.request_key = crypto.randomUUID();
    dialog.value = true;
}
function save() {
    if (!selected.value) return;
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            dialog.value = false;
        },
    };
    if (mode.value === 'payment')
        payment.post(recordPayment(props.member.id).url, options);
    else
        management.patch(
            change({ member: props.member.id, purchase: selected.value.id })
                .url,
            options,
        );
}
</script>
<template>
    <Head :title="member.name + ' - Purchases'" />
    <div class="@container space-y-5 p-4 sm:p-6">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">{{ member.name }}</h1>
                <p class="text-sm text-muted-foreground">
                    Subscriptions, membership plans and training packages
                </p>
            </div>
            <Button variant="outline" as-child
                ><Link :href="show(member.id)">Member profile</Link></Button
            >
        </header>
        <details open class="space-y-4">
            <summary class="cursor-pointer font-semibold">
                Renew membership &amp; record payment
            </summary>
            <MemberCheckout v-bind="props" />
        </details>
        <section class="space-y-3 rounded-xl border bg-card p-4">
            <h2 class="font-semibold">Membership plans & training balances</h2>
            <RecordList
                :rows="purchases.data"
                row-key="id"
                caption="Purchased benefits and packages"
                :columns="[
                    { key: 'plan', label: 'Offering' },
                    { key: 'dates', label: 'Validity' },
                    { key: 'balance', label: 'Balance' },
                    { key: 'status', label: 'Status' },
                    { key: 'sessions', label: 'Sessions' },
                    { key: 'actions', label: 'Actions' },
                ]"
                ><template #cell-plan="{ row }"
                    ><p class="font-medium">{{ row.plan_name }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{ price(row.price_centavos) }} · {{ row.kind }}
                    </p>
                    <details
                        v-if="row.snapshot.discounts?.length"
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        <summary class="cursor-pointer">
                            Price breakdown
                        </summary>
                        <p>
                            Original: {{ price(row.original_price_centavos) }}
                        </p>
                        <p v-for="(d, i) in row.snapshot.discounts" :key="i">
                            {{ d.reason }}: −{{ price(d.savings_centavos) }} on
                            {{ price(d.basis_centavos)
                            }}<span v-if="d.vat_exemption_centavos"
                                >; VAT exemption
                                {{ price(d.vat_exemption_centavos) }}</span
                            >
                        </p>
                    </details></template
                ><template #cell-dates="{ row }"
                    ><p>{{ row.startsOn }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{ row.validThrough }}
                    </p></template
                ><template #cell-balance="{ row }">{{
                    price(row.balance)
                }}</template
                ><template #cell-status="{ row }">{{ row.status }}</template
                ><template #cell-sessions="{ row }">{{
                    row.kind === 'package'
                        ? row.remaining +
                          ' remaining · ' +
                          row.used_sessions +
                          ' used'
                        : '—'
                }}</template
                ><template #cell-actions="{ row }"
                    ><div class="flex flex-wrap justify-end gap-1">
                        <Button
                            v-if="
                                paymentsEnabled &&
                                row.balance > 0 &&
                                row.status !== 'Cancelled'
                            "
                            size="sm"
                            variant="outline"
                            @click="open(row, 'payment')"
                            >Pay</Button
                        ><Button
                            v-if="canManage && row.status !== 'Cancelled'"
                            size="sm"
                            variant="ghost"
                            @click="open(row, 'manage')"
                            >Manage</Button
                        >
                    </div></template
                ></RecordList
            >
            <p
                v-if="!purchases.data.length"
                class="text-sm text-muted-foreground"
            >
                No benefit or training purchases yet. Gym access history stays
                on the member profile.
            </p>
            <nav aria-label="Purchase history pagination" class="flex gap-2">
                <Button
                    v-if="purchases.prev_page_url"
                    variant="outline"
                    as-child
                    ><Link :href="purchases.prev_page_url"
                        >Previous</Link
                    ></Button
                ><Button
                    v-if="purchases.next_page_url"
                    variant="outline"
                    as-child
                    ><Link :href="purchases.next_page_url">Next</Link></Button
                >
            </nav>
            <p class="text-xs text-muted-foreground">
                Completed linked appointments use one fully paid session.
                Cancellations/no-shows use none. Expired/cancelled packages
                cannot be used or transferred. Refunds are handled offline;
                voiding a payment does not send a refund.
            </p>
        </section>
        <section v-if="redemptions.length" class="space-y-3">
            <h2 class="font-semibold">Recent session use</h2>
            <RecordList
                :rows="redemptions"
                row-key="id"
                caption="Recent session use"
                :columns="[
                    { key: 'date', label: 'Session date' },
                    { key: 'appointment', label: 'Appointment' },
                ]"
            >
                <template #cell-date="{ row }">{{ row.date }}</template>
                <template #cell-appointment="{ row }"
                    ><Link
                        :href="
                            appointments({
                                query: {
                                    appointment: row.appointmentId,
                                    date: '',
                                },
                            })
                        "
                        class="underline"
                        >View appointment</Link
                    ></template
                >
            </RecordList>
        </section>
        <Dialog v-model:open="dialog"
            ><DialogContent
                ><DialogHeader
                    ><DialogTitle>{{
                        mode === 'payment'
                            ? 'Record payment'
                            : 'Manage purchase'
                    }}</DialogTitle
                    ><DialogDescription>{{
                        selected?.plan_name
                    }}</DialogDescription></DialogHeader
                >
                <form class="space-y-4" @submit.prevent="save">
                    <template v-if="mode === 'payment'"
                        ><label class="block space-y-1 text-sm"
                            >Amount (PHP)<Input
                                v-model="payment.amount_php"
                                inputmode="decimal"
                                required /></label
                        ><label class="block space-y-1 text-sm"
                            >Method<NativeSelect
                                aria-label="Method"
                                v-model="payment.method"
                                ><option value="cash">Cash</option>
                                <option value="gcash">GCash</option>
                                <option value="maya">Maya</option>
                                <option value="bank-transfer">
                                    Bank transfer
                                </option></NativeSelect
                            ></label
                        ><label class="block space-y-1 text-sm"
                            >Reference (optional)<Input
                                v-model="payment.reference"
                                maxlength="100" /></label
                        ><InputError
                            v-for="(error, key) in payment.errors"
                            :key="key"
                            :message="error" /></template
                    ><template v-else
                        ><label class="block space-y-1 text-sm"
                            >Action<NativeSelect
                                aria-label="Action"
                                v-model="management.action"
                                ><option value="cancel">
                                    Cancel purchase · freeze remaining use
                                </option>
                                <option
                                    v-if="selected?.kind === 'package'"
                                    value="adjust"
                                >
                                    Adjust remaining sessions
                                </option></NativeSelect
                            ></label
                        ><label
                            v-if="management.action === 'adjust'"
                            class="block space-y-1 text-sm"
                            >Sessions to add/remove<Input
                                v-model="management.adjustment"
                                type="number"
                                min="-10000"
                                max="10000" /></label
                        ><label class="block space-y-1 text-sm"
                            >Reason<Input
                                v-model="management.reason"
                                maxlength="500"
                                required
                        /></label>
                        <p class="text-sm text-muted-foreground">
                            Cancellation is final and sends no refund. Original
                            purchased terms stay unchanged.
                        </p>
                        <InputError
                            v-for="(error, key) in management.errors"
                            :key="key"
                            :message="error" /></template
                    ><Button
                        :disabled="management.processing || payment.processing"
                        >{{
                            mode === 'payment'
                                ? 'Record payment'
                                : 'Confirm change'
                        }}</Button
                    >
                </form></DialogContent
            ></Dialog
        >
    </div>
</template>
