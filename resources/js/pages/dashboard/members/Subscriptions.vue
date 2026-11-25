<script setup lang="ts">
import { index as purchases } from '@/routes/purchases';
import RecordMetadata from '@/components/RecordMetadata.vue';
import { formatDisplayDate } from '@/lib/date-format';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Building2,
    CalendarDays,
    CreditCard,
    History,
} from '@lucide/vue';
import { TabsRoot, TabsList, TabsTrigger, TabsContent } from 'reka-ui';
import { computed, ref } from 'vue';
import MemberController from '@/actions/App/Http/Controllers/Dashboard/MemberController';
import MemberInformation from '@/components/members/MemberInformation.vue';
import MemberMemberships from '@/components/members/MemberMemberships.vue';
import MemberPaymentForm from '@/components/members/MemberPaymentForm.vue';
import MemberPaymentHistory from '@/components/members/MemberPaymentHistory.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type {
    MembershipData,
    PaymentFormData,
    PaymentHistoryData,
} from '@/types/member-profile';
const props = defineProps<
    MembershipData & {
        paymentForm: PaymentFormData | null;
        paymentHistory: PaymentHistoryData | null;
    }
>();
defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Members', href: MemberController.index() }],
    },
});
const page = usePage();
const requested = new URL(page.url, 'http://localhost').searchParams.get('tab');
const tab = ref(
    requested === 'membership' ||
        requested === 'history' ||
        (requested === 'payment' && props.paymentForm)
        ? requested
        : 'overview',
);
const initials = computed(() =>
    props.member.name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase(),
);
const currency = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
});
const statusBadgeClass = (status: string) =>
    status === 'Active'
        ? 'border-transparent bg-emerald-500/15 text-emerald-700 dark:text-emerald-400'
        : status === 'Scheduled'
          ? 'border-transparent bg-amber-500/15 text-amber-700 dark:text-amber-300'
          : '';
const overviewHistory = computed(() =>
    [
        ...props.subscriptions.data.slice(0, 5).map((period) => ({
            id: `membership-${period.id}`,
            type: 'Membership',
            title: period.plan_name,
            at: period.createdAt,
            createdAt: period.createdAt,
            createdBy: period.createdBy,
            detail: `${formatDisplayDate(period.startsOn)} – ${formatDisplayDate(period.validThrough)}`,
            amount: currency.format(period.price_centavos / 100),
            status: period.status,
        })),
        ...(props.paymentHistory?.payments.data.slice(0, 5) ?? []).map(
            (payment) => ({
                id: `payment-${payment.id}`,
                type: 'Payment',
                title: payment.planName,
                at: payment.paidAt,
                createdAt: payment.createdAt,
                createdBy: payment.createdBy,
                detail: `${payment.method} · ${payment.status === 'voided' ? 'Voided' : 'Recorded'}`,
                amount: currency.format(payment.amountCentavos / 100),
                status: payment.status === 'voided' ? 'Voided' : 'Recorded',
            }),
        ),
    ].sort((a, b) => Date.parse(b.at) - Date.parse(a.at)),
);
</script>
<template>
    <Head :title="`${member.name} - Member profile`" />
    <div class="space-y-5 p-4 sm:p-6">
        <section class="overflow-hidden rounded-xl border bg-card">
            <div class="flex justify-end border-b px-4 py-2 sm:px-6">
                <Button variant="outline" as-child>
                    <Link :href="MemberController.index()">
                        <ArrowLeft class="size-4" />Back to members
                    </Link>
                </Button>
            </div>
            <header
                class="flex flex-col gap-5 p-4 sm:p-6 xl:flex-row xl:items-center xl:justify-between"
            >
                <div class="flex min-w-0 items-center gap-4">
                    <span
                        class="flex size-16 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xl font-semibold text-primary"
                        aria-hidden="true"
                        >{{ initials }}</span
                    >
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-2xl font-semibold break-words">
                                {{ member.name }}
                            </h1>
                            <Badge variant="outline">{{
                                member.membershipStatus
                            }}</Badge>
                        </div>
                        <p
                            class="mt-1 text-sm break-words text-muted-foreground"
                        >
                            {{ member.email || 'No email' }} ·
                            {{ member.mobile || 'No mobile number' }}
                        </p>
                        <Link
                            :href="purchases(member.id)"
                            class="mt-2 inline-flex min-h-11 items-center text-sm underline underline-offset-4"
                            >Subscriptions & packages</Link
                        >
                    </div>
                </div>
                <dl class="grid gap-3 sm:grid-cols-3">
                    <div
                        class="flex items-center gap-3 rounded-lg bg-muted/50 p-3"
                    >
                        <Building2 class="size-5 shrink-0 text-primary" />
                        <div>
                            <dd class="text-sm font-medium">
                                {{ member.branchName }}
                            </dd>
                            <dt class="text-xs text-muted-foreground">
                                Home branch
                            </dt>
                        </div>
                    </div>
                    <div
                        class="flex items-center gap-3 rounded-lg bg-muted/50 p-3"
                    >
                        <CreditCard class="size-5 shrink-0 text-primary" />
                        <div>
                            <dd class="text-sm font-medium">
                                {{ member.planName || 'No plan' }}
                            </dd>
                            <dt class="text-xs text-muted-foreground">
                                Membership plan
                            </dt>
                        </div>
                    </div>
                    <div
                        class="flex items-center gap-3 rounded-lg bg-muted/50 p-3"
                    >
                        <CalendarDays class="size-5 shrink-0 text-primary" />
                        <div>
                            <dd class="text-sm font-medium">
                                {{ formatDisplayDate(member.renewalDue) }}
                            </dd>
                            <dt class="text-xs text-muted-foreground">
                                Renewal due
                            </dt>
                        </div>
                    </div>
                </dl>
            </header>
            <TabsRoot v-model="tab">
                <div class="border-b px-4 sm:px-6">
                    <TabsList
                        aria-label="Member profile sections"
                        class="flex flex-wrap gap-1"
                    >
                        <TabsTrigger
                            v-for="item in [
                                { value: 'overview', label: 'Overview' },
                                {
                                    value: 'membership',
                                    label: 'Subscriptions & plans',
                                },
                                ...(paymentForm
                                    ? [{ value: 'payment', label: 'Payment' }]
                                    : []),
                                { value: 'history', label: 'History' },
                            ]"
                            :key="item.value"
                            :value="item.value"
                            class="min-h-11 border-b-2 border-transparent px-3 text-sm text-muted-foreground outline-none focus-visible:ring-2 focus-visible:ring-ring data-[state=active]:border-primary data-[state=active]:font-semibold data-[state=active]:text-primary"
                            >{{ item.label }}</TabsTrigger
                        >
                    </TabsList>
                </div>
                <TabsContent value="overview" class="p-4 outline-none sm:p-5">
                    <MemberInformation
                        :member="member"
                        :can-manage="canManage"
                    />
                    <div
                        class="mt-4 grid gap-4"
                        :class="paymentForm ? 'md:grid-cols-2' : 'grid-cols-1'"
                    >
                        <section class="rounded-xl border bg-card p-4 sm:p-5">
                            <h2
                                class="mb-4 flex items-center gap-2 border-b pb-3 text-base font-semibold"
                            >
                                <CreditCard class="size-5" />Membership plan
                            </h2>
                            <p
                                v-if="!member.planName"
                                class="text-sm text-muted-foreground"
                            >
                                No membership assigned.
                            </p>
                            <dl
                                v-else
                                class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-5 gap-y-3 text-sm"
                            >
                                <dt class="text-muted-foreground">Plan</dt>
                                <dd class="break-words">
                                    {{ member.planName }}
                                </dd>
                                <dt class="text-muted-foreground">
                                    Agreed amount
                                </dt>
                                <dd>
                                    {{
                                        currency.format(
                                            (member.planAmountCentavos ?? 0) /
                                                100,
                                        )
                                    }}
                                </dd>
                                <dt class="text-muted-foreground">Duration</dt>
                                <dd>{{ member.planDuration }}</dd>
                                <dt class="text-muted-foreground">
                                    Start date
                                </dt>
                                <dd>
                                    {{ formatDisplayDate(member.startsOn) }}
                                </dd>
                                <dt class="text-muted-foreground">
                                    Valid through
                                </dt>
                                <dd>
                                    {{ formatDisplayDate(member.validThrough) }}
                                </dd>
                                <dt class="text-muted-foreground">Status</dt>
                                <dd>{{ member.membershipStatus }}</dd>
                            </dl>
                        </section>
                        <section
                            v-if="paymentForm"
                            class="rounded-xl border bg-card p-4 sm:p-5"
                        >
                            <h2
                                class="mb-4 flex items-center gap-2 text-base font-semibold"
                            >
                                <CreditCard class="size-5" />Payments
                            </h2>
                            <p class="mb-4 text-xs text-muted-foreground">
                                To renew this member and record the payment
                                together, use the renewal checkout.
                            </p>
                            <Button
                                v-if="canManage && plans.length && canRenew"
                                variant="outline"
                                as-child
                                class="mb-4"
                            >
                                <Link :href="purchases(member.id)">
                                    Renew &amp; record payment
                                </Link>
                            </Button>
                            <p
                                v-if="canManage && plans.length && canRenew"
                                class="mb-4 text-xs text-muted-foreground"
                            >
                                Use the form below only for a payment on an
                                existing unpaid purchase.
                            </p>
                            <MemberPaymentForm v-bind="paymentForm" embedded />
                        </section>
                        <section
                            class="rounded-xl border p-4 sm:p-5 md:col-span-2"
                        >
                            <header
                                class="mb-4 flex flex-wrap items-center justify-between gap-2"
                            >
                                <h2
                                    class="flex items-center gap-2 text-base font-semibold"
                                >
                                    <History class="size-5" />Membership &amp;
                                    payment history
                                </h2>
                                <div class="flex flex-wrap gap-2">
                                    <Button
                                        v-if="paymentHistory"
                                        variant="outline"
                                        @click="tab = 'history'"
                                    >
                                        View payment history
                                    </Button>
                                </div>
                            </header>
                            <p
                                v-if="!overviewHistory.length"
                                class="text-sm text-muted-foreground"
                            >
                                No membership or payment history yet.
                            </p>
                            <ul v-else class="divide-y">
                                <li
                                    v-for="item in overviewHistory"
                                    :key="item.id"
                                    class="flex flex-wrap items-center justify-between gap-3 py-3"
                                >
                                    <div class="min-w-0">
                                        <p
                                            class="text-sm font-medium break-words"
                                        >
                                            {{ item.title }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ item.type }} · {{ item.at }} ·
                                            {{ item.detail }}
                                        </p>
                                        <RecordMetadata
                                            :created-at="item.createdAt"
                                            :created-by="item.createdBy"
                                        />
                                    </div>
                                    <div
                                        class="flex shrink-0 items-center gap-3"
                                    >
                                        <span class="text-sm">{{
                                            item.amount
                                        }}</span>
                                        <Badge variant="outline">{{
                                            item.status
                                        }}</Badge>
                                    </div>
                                </li>
                            </ul>
                        </section>
                    </div>
                </TabsContent>
                <TabsContent value="membership" class="p-4 outline-none sm:p-6"
                    ><h2 class="mb-5 text-xl font-semibold">
                        Current subscriptions &amp; plans
                    </h2>
                    <p
                        v-if="
                            !currentSubscriptions.length &&
                            !currentMembershipPlans.length
                        "
                        class="rounded-lg border py-8 text-center text-sm text-muted-foreground"
                    >
                        No current subscriptions or membership plans.
                    </p>
                    <div v-else class="grid items-start gap-4 md:grid-cols-2">
                        <section class="rounded-xl border p-4 sm:p-5">
                            <h3 class="mb-4 text-base font-semibold">
                                Gym access plan
                            </h3>
                            <p
                                v-if="!currentSubscriptions.length"
                                class="text-sm text-muted-foreground"
                            >
                                No current gym access plans.
                            </p>
                            <ul v-else class="divide-y">
                                <li
                                    v-for="item in currentSubscriptions"
                                    :key="item.id"
                                    class="flex items-center justify-between gap-3 py-3"
                                >
                                    <div>
                                        <p class="font-medium">
                                            {{ item.planName }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                formatDisplayDate(item.startsOn)
                                            }}
                                            –
                                            {{
                                                formatDisplayDate(
                                                    item.validThrough,
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <Badge
                                        variant="outline"
                                        :class="statusBadgeClass(item.status)"
                                        >{{ item.status }}</Badge
                                    >
                                </li>
                            </ul>
                        </section>
                        <section class="rounded-xl border p-4 sm:p-5">
                            <h3 class="mb-4 text-base font-semibold">
                                Membership plans
                            </h3>
                            <p
                                v-if="!currentMembershipPlans.length"
                                class="text-sm text-muted-foreground"
                            >
                                No current membership plans.
                            </p>
                            <ul v-else class="divide-y">
                                <li
                                    v-for="item in currentMembershipPlans"
                                    :key="item.id"
                                    class="flex items-center justify-between gap-3 py-3"
                                >
                                    <div>
                                        <p class="font-medium">
                                            {{ item.planName }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                currency.format(
                                                    item.priceCentavos / 100,
                                                )
                                            }}
                                            ·
                                            {{
                                                formatDisplayDate(item.startsOn)
                                            }}
                                            –
                                            {{
                                                item.validThrough
                                                    ? formatDisplayDate(
                                                          item.validThrough,
                                                      )
                                                    : 'No expiry'
                                            }}
                                        </p>
                                    </div>
                                    <Badge
                                        variant="outline"
                                        :class="statusBadgeClass(item.status)"
                                        >{{ item.status }}</Badge
                                    >
                                </li>
                            </ul>
                        </section>
                    </div>
                </TabsContent>
                <TabsContent
                    v-if="paymentForm"
                    value="payment"
                    class="p-4 outline-none sm:p-6"
                    ><section class="max-w-2xl space-y-4">
                        <h2 class="text-xl font-semibold">
                            Apply payment to an existing purchase
                        </h2>
                        <MemberPaymentForm
                            v-bind="paymentForm"
                            embedded
                        /></section
                ></TabsContent>
                <TabsContent value="history" class="p-4 outline-none sm:p-6"
                    ><section class="space-y-5">
                        <h2 class="text-xl font-semibold">History</h2>
                        <MemberMemberships
                            :member="member"
                            :can-manage="canManage"
                            :today="today"
                            :plans="plans"
                            :subscriptions="subscriptions"
                            :membership-plans="membershipPlans"
                        />
                        <MemberPaymentHistory
                            v-if="paymentHistory"
                            v-bind="paymentHistory"
                        />
                        <p v-else class="text-sm text-muted-foreground">
                            Payments are disabled for this gym.
                        </p>
                    </section></TabsContent
                >
            </TabsRoot>
        </section>
    </div>
</template>
