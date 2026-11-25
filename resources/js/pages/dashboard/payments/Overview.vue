<script setup lang="ts">
import { index as purchases } from '@/routes/purchases';
import RecordList from '@/components/RecordList.vue';
import { formatDisplayDate } from '@/lib/date-format';
import { Head, Link, usePage } from '@inertiajs/vue3';
import PaymentOverviewController from '@/actions/App/Http/Controllers/Dashboard/PaymentOverviewController';
import PaymentController from '@/actions/App/Http/Controllers/Dashboard/PaymentController';
import NativeSelect from '@/components/NativeSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Pagination<T> = {
    data: T[];
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
defineProps<{
    filters: {
        view: string;
        date: string;
        branch_id: number | null;
        balance: string;
    };
    summary: { revenueCentavos: number; outstandingCentavos: number };
    branches: { id: number; name: string }[];
    payments: Pagination<{
        id: number;
        createdAt: string;
        createdBy: string;
        memberName: string | null;
        branchName: string | null;
        amountCentavos: number;
        status: string;
        method: string;
        paidAt: string;
        voidedAt: string | null;
        voidedBy: string | null;
    }>;
    balances: Pagination<{
        id: number | string;
        kind: string;
        memberId: number;
        memberName: string | null;
        planName: string;
        startsOn: string;
        balanceCentavos: number;
        overdue: boolean;
    }>;
}>();
const page = usePage();
const currency = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
});
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Payment history', href: PaymentOverviewController() },
        ],
    },
});
</script>

<template>
    <Head title="Payment history" />
    <div class="space-y-5 p-4 sm:p-6">
        <header>
            <h1 class="text-2xl font-semibold">Payment history</h1>
            <p class="text-sm text-muted-foreground">
                Offline payment records and purchase balances in Philippine
                pesos.
            </p>
        </header>
        <form
            v-bind="PaymentOverviewController.form()"
            class="grid gap-4 rounded-xl border bg-card p-4 sm:grid-cols-2 lg:grid-cols-5"
        >
            <div class="space-y-2">
                <Label for="payment-view">Revenue period</Label
                ><NativeSelect
                    id="payment-view"
                    name="view"
                    :value="filters.view"
                    ><option value="day">Daily</option>
                    <option value="month">Monthly</option></NativeSelect
                >
            </div>
            <div class="space-y-2">
                <Label for="payment-date">Date</Label
                ><Input
                    id="payment-date"
                    name="date"
                    type="date"
                    :default-value="filters.date"
                    required
                />
            </div>
            <div class="space-y-2">
                <Label for="payment-branch">Branch</Label
                ><NativeSelect
                    id="payment-branch"
                    name="branch_id"
                    :value="filters.branch_id ?? ''"
                    ><option v-if="!page.props.currentBranch" value="">
                        All branches
                    </option>
                    <option
                        v-for="branch in branches"
                        :key="branch.id"
                        :value="branch.id"
                    >
                        {{ branch.name }}
                    </option></NativeSelect
                >
            </div>
            <div class="space-y-2">
                <Label for="payment-balance">Purchase balances</Label
                ><NativeSelect
                    id="payment-balance"
                    name="balance"
                    :value="filters.balance"
                    ><option value="unpaid">All unpaid</option>
                    <option value="overdue">Overdue only</option></NativeSelect
                >
            </div>
            <Button type="submit" class="self-end">Apply filters</Button>
        </form>
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl border bg-card p-4">
                <p class="text-sm text-muted-foreground">
                    {{ filters.view === 'month' ? 'Monthly' : 'Daily' }}
                    recorded revenue
                </p>
                <p class="text-2xl font-semibold">
                    {{ currency.format(summary.revenueCentavos / 100) }}
                </p>
                <p class="text-sm text-muted-foreground">
                    Voided payments are excluded. Dates use Manila time.
                </p>
            </div>
            <div class="rounded-xl border bg-card p-4">
                <p class="text-sm text-muted-foreground">
                    {{ filters.balance === 'overdue' ? 'Overdue' : 'Unpaid' }}
                    balance
                </p>
                <p class="text-2xl font-semibold">
                    {{ currency.format(summary.outstandingCentavos / 100) }}
                </p>
                <p class="text-sm text-muted-foreground">
                    Current balances across all dates. Overdue means the
                    membership has started and remains unpaid.
                </p>
            </div>
        </div>
        <section class="space-y-3" aria-labelledby="payments-heading">
            <h2 id="payments-heading" class="text-lg font-semibold">
                Payment records
            </h2>
            <p v-if="!payments.data.length">No payments in this period.</p>
            <RecordList
                :rows="payments.data"
                :columns="[
                    { key: 'memberName', label: 'Member' },
                    { key: 'paidAt', label: 'Date (Manila)' },
                    { key: 'amountCentavos', label: 'Amount received' },
                    { key: 'branchName', label: 'Branch' },
                    { key: 'method', label: 'Method' },
                    { key: 'status', label: 'Status' },
                    { key: 'actions', label: 'Actions' },
                ]"
                caption="Payment records"
                v-if="payments.data.length"
                row-key="id"
                wide
                ><template #cell-memberName="{ row: payment }"
                    ><p class="font-medium">
                        {{ payment.memberName }}
                    </p></template
                ><template #cell-paidAt="{ row: payment }">{{
                    formatDisplayDate(payment.paidAt)
                }}</template
                ><template #cell-amountCentavos="{ row: payment }">{{
                    currency.format(payment.amountCentavos / 100)
                }}</template
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
                    <p
                        v-if="payment.voidedAt"
                        class="mt-2 text-xs text-muted-foreground"
                    >
                        Voided:
                        {{ formatDisplayDate(payment.voidedAt) }} (Manila)<br />
                        Voided by: {{ payment.voidedBy || 'Not recorded' }}
                    </p></template
                ><template #cell-actions="{ row: payment }"
                    ><Button variant="outline" as-child
                        ><Link :href="PaymentController.show(payment.id)"
                            >View record</Link
                        ></Button
                    ></template
                ></RecordList
            >
            <nav
                v-if="payments.prev_page_url || payments.next_page_url"
                class="flex gap-3"
                aria-label="Payment records pagination"
            >
                <Button v-if="payments.prev_page_url" variant="outline" as-child
                    ><Link :href="payments.prev_page_url"
                        >Previous</Link
                    ></Button
                ><Button
                    v-if="payments.next_page_url"
                    variant="outline"
                    as-child
                    ><Link :href="payments.next_page_url">Next</Link></Button
                >
            </nav>
        </section>
        <section class="space-y-3" aria-labelledby="balances-heading">
            <h2 id="balances-heading" class="text-lg font-semibold">
                {{
                    filters.balance === 'overdue'
                        ? 'Overdue memberships'
                        : 'Unpaid memberships'
                }}
            </h2>
            <p v-if="!balances.data.length">
                No outstanding memberships match this filter.
            </p>
            <RecordList
                :rows="balances.data"
                :columns="[
                    { key: 'memberName', label: 'Member' },
                    { key: 'planName', label: 'Offering' },
                    { key: 'startsOn', label: 'Starts on' },
                    { key: 'balanceCentavos', label: 'Unpaid balance' },
                    { key: 'status', label: 'Status' },
                    { key: 'actions', label: 'Actions' },
                ]"
                caption="Unpaid purchase balances"
                v-if="balances.data.length"
                row-key="id"
                wide
                ><template #cell-memberName="{ row: balance }"
                    ><p class="font-medium">
                        {{ balance.memberName }}
                    </p></template
                ><template #cell-startsOn="{ row: balance }">{{
                    formatDisplayDate(balance.startsOn)
                }}</template
                ><template #cell-balanceCentavos="{ row: balance }">{{
                    currency.format(balance.balanceCentavos / 100)
                }}</template
                ><template #cell-status="{ row: balance }"
                    ><span
                        class="rounded-full px-2 py-1 text-xs"
                        :class="
                            balance.overdue
                                ? 'bg-amber-500/15 text-amber-700 dark:text-amber-300'
                                : 'bg-muted text-muted-foreground'
                        "
                        >{{ balance.overdue ? 'Overdue' : 'Scheduled' }}</span
                    ></template
                ><template #cell-actions="{ row: balance }"
                    ><Button variant="outline" as-child
                        ><Link
                            :href="
                                balance.kind === 'subscription'
                                    ? PaymentController.create(balance.memberId)
                                    : purchases(balance.memberId)
                            "
                            >Member payment</Link
                        ></Button
                    ></template
                ></RecordList
            >
            <nav
                v-if="balances.prev_page_url || balances.next_page_url"
                class="flex gap-3"
                aria-label="Purchase balances pagination"
            >
                <Button v-if="balances.prev_page_url" variant="outline" as-child
                    ><Link :href="balances.prev_page_url"
                        >Previous</Link
                    ></Button
                ><Button
                    v-if="balances.next_page_url"
                    variant="outline"
                    as-child
                    ><Link :href="balances.next_page_url">Next</Link></Button
                >
            </nav>
        </section>
    </div>
</template>
