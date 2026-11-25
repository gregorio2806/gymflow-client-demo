<script setup lang="ts">
import RecordList from '@/components/RecordList.vue';
import { formatDisplayDate } from '@/lib/date-format';
import { Form, Link } from '@inertiajs/vue3';
import PaymentController from '@/actions/App/Http/Controllers/Dashboard/PaymentController';
import InputError from '@/components/InputError.vue';
import { memberTabUrl } from '@/lib/member-tab-url';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineProps<{
    member: { id: number; name: string };
    payments: {
        data: {
            id: number;
            createdAt: string;
            createdBy: string;
            amountCentavos: number;
            planName: string;
            discountCentavos: number;
            discountPercent: number;
            discountScope: string | null;
            discountBaseCentavos: number | null;
            method: string;
            reference: string | null;
            status: string;
            paidAt: string;
            branchName: string | null;
            voidReason: string | null;
            voidedAt: string | null;
            voidedBy: string | null;
            canVoid: boolean;
        }[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}>();
const currency = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
});
</script>
<template>
    <div class="space-y-5">
        <p v-if="!payments.data.length">No payments recorded.</p>
        <RecordList
            :rows="payments.data"
            :columns="[
                { key: 'paidAt', label: 'Date (Manila)', width: '14%' },
                { key: 'planName', label: 'Offering', width: '18%' },
                {
                    key: 'amountCentavos',
                    label: 'Amount received',
                    width: '14%',
                },
                { key: 'method', label: 'Method', width: '10%' },
                { key: 'branchName', label: 'Branch', width: '12%' },
                { key: 'reference', label: 'Reference', width: '12%' },
                { key: 'status', label: 'Status', width: '8%' },
                { key: 'actions', label: 'Actions', width: '12%' },
            ]"
            caption="Member payment history"
            v-if="payments.data.length"
            row-key="id"
            wide
            ><template #cell-paidAt="{ row: payment }">{{
                formatDisplayDate(payment.paidAt)
            }}</template
            ><template #cell-amountCentavos="{ row: payment }"
                ><p class="font-medium">
                    {{ currency.format(payment.amountCentavos / 100) }}
                </p>
                <p
                    v-if="payment.discountPercent > 0"
                    class="mt-1 text-xs text-muted-foreground"
                >
                    {{ payment.discountPercent }}% off
                    {{
                        payment.discountScope === 'membership'
                            ? 'membership'
                            : 'this payment'
                    }}:
                    {{ currency.format(payment.discountCentavos / 100) }} saved
                    from
                    {{
                        currency.format(
                            (payment.discountBaseCentavos ?? 0) / 100,
                        )
                    }}.
                </p></template
            ><template #cell-status="{ row: payment }"
                ><span
                    class="rounded-full px-2 py-1 text-xs"
                    :class="
                        payment.status === 'voided'
                            ? 'bg-muted text-muted-foreground'
                            : 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400'
                    "
                    >{{
                        payment.status === 'voided' ? 'Voided' : 'Recorded'
                    }}</span
                >
                <p v-if="payment.voidReason" class="mt-2 text-xs">
                    {{ payment.voidReason }}
                    <span class="text-muted-foreground">{{
                        formatDisplayDate(payment.voidedAt)
                    }}</span>
                    <span
                        v-if="payment.voidedBy"
                        class="block text-muted-foreground"
                        >Voided by: {{ payment.voidedBy }}</span
                    >
                </p></template
            ><template #cell-actions="{ row: payment }"
                ><Button variant="outline" as-child
                    ><Link :href="PaymentController.show(payment.id)"
                        >View record</Link
                    ></Button
                >
                <details v-if="payment.canVoid" class="space-y-3">
                    <summary
                        class="flex min-h-11 cursor-pointer items-center text-sm font-medium text-destructive"
                    >
                        Void payment
                    </summary>
                    <p class="text-sm">
                        Voiding excludes this payment from the membership
                        balance and revenue, and restores its discounted
                        balance. The record stays in history. No refund is sent.
                    </p>
                    <Form
                        v-bind="PaymentController.voidMethod.form(payment.id)"
                        v-slot="{ errors, processing }"
                        class="space-y-3"
                    >
                        <Label :for="`void-reason-${payment.id}`"
                            >Reason for voiding</Label
                        >
                        <Input
                            :id="`void-reason-${payment.id}`"
                            name="void_reason"
                            required
                            maxlength="500"
                            :disabled="processing"
                        />
                        <InputError
                            :message="errors.void_reason ?? errors.branch_id"
                        />
                        <Button
                            type="submit"
                            variant="destructive"
                            :disabled="processing"
                            >Confirm void</Button
                        >
                    </Form>
                </details></template
            ></RecordList
        >
        <nav
            v-if="payments.prev_page_url || payments.next_page_url"
            class="flex gap-3"
            aria-label="Payment history pagination"
        >
            <Button v-if="payments.prev_page_url" variant="outline" as-child
                ><Link :href="memberTabUrl(payments.prev_page_url, 'history')"
                    >Previous</Link
                ></Button
            >
            <Button v-if="payments.next_page_url" variant="outline" as-child
                ><Link :href="memberTabUrl(payments.next_page_url, 'history')"
                    >Next</Link
                ></Button
            >
        </nav>
    </div>
</template>
