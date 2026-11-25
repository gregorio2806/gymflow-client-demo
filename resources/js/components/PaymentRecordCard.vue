<script setup lang="ts">
import RecordMetadata from '@/components/RecordMetadata.vue';
import { formatDisplayDate } from '@/lib/date-format';
export type PaymentRecord = {
    id: number;
    createdAt: string;
    createdBy: string;
    gymName: string;
    branchName: string | null;
    memberName: string | null;
    planName: string | null;
    startsOn: string | null;
    validThrough: string | null;
    amountCentavos: number;
    discountCentavos: number;
    discountPercent: number;
    discountScope: string | null;
    discountBaseCentavos: number | null;
    method: string;
    reference: string | null;
    status: string;
    paidAt: string;
    voidReason: string | null;
    voidedAt: string | null;
    voidedBy: string | null;
};
defineProps<{ record: PaymentRecord }>();
const currency = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
});
const methods: Record<string, string> = {
    cash: 'Cash',
    gcash: 'GCash',
    maya: 'Maya',
    'bank-transfer': 'Bank transfer',
};
</script>

<template>
    <article
        class="space-y-4 rounded-xl border bg-card p-4 text-card-foreground sm:p-6"
    >
        <header class="space-y-2">
            <h2 class="text-lg font-semibold break-words">
                {{ record.gymName }}
            </h2>
            <p class="break-words">{{ record.branchName }}</p>
            <h3 class="font-semibold">
                Payment Record — not an official invoice
            </h3>
            <p class="text-sm">Record #{{ record.id }}</p>
            <RecordMetadata
                :created-at="record.createdAt"
                :created-by="record.createdBy"
            />
        </header>
        <p
            v-if="record.status === 'voided'"
            class="rounded border p-3 font-semibold"
        >
            VOIDED — excluded from payments and revenue
        </p>
        <dl class="space-y-3 text-sm">
            <div>
                <dt class="font-medium">Member</dt>
                <dd class="break-words">{{ record.memberName }}</dd>
            </div>
            <div>
                <dt class="font-medium">Membership</dt>
                <dd class="break-words">
                    {{ record.planName }} ·
                    {{ formatDisplayDate(record.startsOn) }} through
                    {{ formatDisplayDate(record.validThrough) }}
                </dd>
            </div>
            <div>
                <dt class="font-medium">Amount received (PHP)</dt>
                <dd class="text-lg font-semibold">
                    {{ currency.format(record.amountCentavos / 100) }}
                </dd>
            </div>
            <div>
                <template v-if="record.discountPercent > 0"
                    ><dt class="font-medium">Discount</dt>
                    <dd>
                        {{ record.discountPercent }}% on
                        {{
                            record.discountScope === 'membership'
                                ? 'the whole membership'
                                : 'this payment'
                        }}. Original amount:
                        {{
                            currency.format(
                                (record.discountBaseCentavos ?? 0) / 100,
                            )
                        }}. Savings:
                        {{ currency.format(record.discountCentavos / 100) }}.
                    </dd></template
                >
                <dt class="font-medium">Method</dt>
                <dd>{{ methods[record.method] ?? record.method }}</dd>
            </div>
            <div>
                <dt class="font-medium">Recorded at</dt>
                <dd>{{ formatDisplayDate(record.paidAt) }} (Manila time)</dd>
            </div>
            <div v-if="record.reference">
                <dt class="font-medium">Reference</dt>
                <dd class="break-words">{{ record.reference }}</dd>
            </div>
            <div v-if="record.voidReason">
                <dt class="font-medium">Void reason</dt>
                <dd class="break-words">{{ record.voidReason }}</dd>
            </div>
            <div v-if="record.voidedAt">
                <dt class="font-medium">Voided at</dt>
                <dd>{{ formatDisplayDate(record.voidedAt) }} (Manila time)</dd>
            </div>
            <div v-if="record.voidedBy">
                <dt class="font-medium">Voided by</dt>
                <dd>{{ record.voidedBy }}</dd>
            </div>
        </dl>
        <p class="text-sm">
            This is a record of an offline payment. It is not an official
            invoice and does not replace an invoice issued by the gym.
        </p>
    </article>
</template>
