<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Plus, CreditCard, Crown, Dumbbell, Percent } from '@lucide/vue';
import {
    store as storeDiscount,
    update as updateDiscount,
} from '@/routes/pricing/discounts';
import Controller from '@/actions/App/Http/Controllers/Dashboard/MembershipPlanController';
import RecordList from '@/components/RecordList.vue';
import MembershipPlanActions from '@/components/MembershipPlanActions.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import type { MembershipPlanRow } from '@/types/membership-plans';
import type { Offering, DiscountRule, OfferingKind } from '@/types/pricing';
const props = defineProps<{
    plans: {
        data: (MembershipPlanRow & Offering)[];
        total: number;
        from: number | null;
        to: number | null;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    status: string;
    tab: string;
    search: string;
    canManage: boolean;
    discountRules: DiscountRule[];
    discountOfferings: { id: number; name: string; kind: OfferingKind }[];
}>();
defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Plans & Pricing', href: Controller.index() }],
    },
});
const tabs = [
    { key: 'subscription', title: 'Gym subscriptions' },
    { key: 'benefit', title: 'Membership plans' },
    { key: 'discounts', title: 'Discounts' },
    { key: 'package', title: 'Training packages' },
];
const sections = [
    {
        key: 'subscription',
        title: 'Gym subscriptions',
        hint: 'Gym access rates. A membership plan is optional.',
        icon: CreditCard,
    },
    {
        key: 'benefit',
        title: 'Membership plans',
        hint: 'A separate membership fee that offers discounts on eligible purchases.',
        icon: Crown,
    },
    {
        key: 'package',
        title: 'Training packages',
        hint: 'Prepaid sessions with independent prices and balances.',
        icon: Dumbbell,
    },
];
const visibleSections = computed(() =>
    sections.filter((s) => props.tab === s.key),
);
const currency = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
});
const price = (c: number) => currency.format(c / 100);
const searchText = ref(props.search);
function filter(tab = props.tab, status = props.status) {
    router.get(
        Controller.index().url,
        { tab, status, search: searchText.value },
        { preserveState: true, preserveScroll: true },
    );
}
const discountOpen = ref(false);
const discountId = ref<number | null>(null);
const discount = useForm({
    name: '',
    category: 'student',
    unit: 'percentage' as 'percentage' | 'peso',
    input: '0',
    requires_verification: true,
    is_active: true,
    product_kinds: ['subscription'] as OfferingKind[],
    plan_ids: [] as number[],
    effective_from: '',
    effective_through: '',
});
function editDiscount(row?: DiscountRule) {
    discountId.value = row?.id ?? null;
    discount.defaults({
        name: row?.name ?? '',
        category: row?.category ?? 'student',
        unit: row?.unit ?? 'percentage',
        input: row ? (row.value / 100).toFixed(2) : '0',
        requires_verification: row?.requires_verification ?? true,
        is_active: row?.is_active ?? true,
        product_kinds: row?.product_kinds ?? ['subscription'],
        plan_ids: row?.plan_ids ?? [],
        effective_from: row?.effective_from ?? '',
        effective_through: row?.effective_through ?? '',
    });
    discount.reset();
    discount.clearErrors();
    discountOpen.value = true;
}
function saveDiscount() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            discountOpen.value = false;
        },
    };
    if (discountId.value)
        discount.patch(updateDiscount(discountId.value).url, options);
    else discount.post(storeDiscount().url, options);
}
</script>
<template>
    <Head title="Plans & Pricing" />
    <div class="@container space-y-4 p-4 sm:p-6">
        <header>
            <h1 class="text-2xl font-semibold">Plans & Pricing</h1>
            <p class="text-sm text-muted-foreground">
                Manage gym access, membership plans, discounts and training
                packages.
            </p>
        </header>
        <nav
            aria-label="Pricing categories"
            class="flex flex-wrap gap-1 border-b pb-2"
        >
            <Button
                v-for="item in tabs"
                :key="item.key"
                :variant="tab === item.key ? 'secondary' : 'ghost'"
                :aria-current="tab === item.key ? 'page' : undefined"
                @click="filter(item.key)"
                >{{ item.title }}</Button
            >
        </nav>
        <form
            v-if="tab !== 'discounts'"
            class="flex flex-wrap items-center gap-2"
            @submit.prevent="filter()"
        >
            <Input
                v-model="searchText"
                aria-label="Search offerings"
                placeholder="Search offerings…"
                class="w-full sm:w-64"
            /><NativeSelect
                :value="status"
                aria-label="Offering availability"
                class="w-full sm:w-40"
                @change="
                    filter(tab, ($event.target as HTMLSelectElement).value)
                "
                ><option value="all">All offerings</option>
                <option value="active">Available</option>
                <option value="inactive">Archived</option></NativeSelect
            ><Button variant="outline" type="submit">Search</Button>
        </form>
        <section
            v-for="section in visibleSections"
            :key="section.key"
            class="space-y-3 rounded-xl border bg-card p-4"
        >
            <header class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-start gap-3">
                    <component
                        :is="section.icon"
                        class="mt-1 size-5 text-primary"
                    />
                    <div>
                        <h2 class="font-semibold">{{ section.title }}</h2>
                        <p class="text-xs text-muted-foreground">
                            {{ section.hint }}
                        </p>
                    </div>
                </div>
                <Button v-if="canManage" size="sm" as-child
                    ><Link
                        :href="
                            Controller.create({ query: { kind: section.key } })
                        "
                        ><Plus class="size-4" />Add
                        {{
                            section.key === 'subscription'
                                ? 'subscription'
                                : section.key === 'benefit'
                                  ? 'membership plan'
                                  : 'package'
                        }}</Link
                    ></Button
                >
            </header>
            <RecordList
                :rows="plans.data.filter((p) => p.kind === section.key)"
                row-key="id"
                :caption="section.title"
                :columns="[
                    { key: 'name', label: 'Name' },
                    { key: 'duration', label: 'Duration' },
                    {
                        key: 'price',
                        label:
                            section.key === 'benefit'
                                ? 'Membership fee'
                                : section.key === 'package'
                                  ? 'Package amount'
                                  : 'Amount',
                    },
                    {
                        key: 'terms',
                        label:
                            section.key === 'benefit'
                                ? 'Subscription discount'
                                : section.key === 'package'
                                  ? 'Sessions'
                                  : 'Branch access',
                    },
                    { key: 'status', label: 'Status' },
                    { key: 'actions', label: 'Actions' },
                ]"
            >
                <template #cell-name="{ row }"
                    ><p class="font-medium">{{ row.name }}</p>
                    <p
                        v-if="row.inclusions"
                        class="text-xs text-muted-foreground"
                    >
                        {{ row.inclusions }}
                    </p></template
                >
                <template #cell-duration="{ row }">{{
                    row.kind === 'package' &&
                    (row.configuration?.duration_mode === 'sessions' ||
                        row.configuration?.unlimited_validity)
                        ? 'Per session'
                        : row.duration_amount + ' ' + row.duration_unit
                }}</template>
                <template #cell-price="{ row }"
                    ><span class="font-semibold">{{
                        price(row.price_centavos)
                    }}</span></template
                >
                <template #cell-terms="{ row }"
                    ><span v-if="row.kind === 'benefit'"
                        >{{
                            row.configuration?.discount_unit === 'peso'
                                ? price(row.configuration?.discount_value ?? 0)
                                : (row.configuration?.discount_value ?? 0) /
                                      100 +
                                  '%'
                        }}
                        off</span
                    ><span v-else-if="row.kind === 'package'"
                        >{{ row.configuration?.sessions }} sessions</span
                    ><span v-else>{{
                        row.branch_ids?.length
                            ? row.branch_ids.length + ' selected branches'
                            : 'All branches'
                    }}</span></template
                >
                <template #cell-status="{ row }"
                    ><span
                        class="rounded-full px-2 py-1 text-xs"
                        :class="
                            row.is_active
                                ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                : 'bg-muted text-muted-foreground'
                        "
                        >{{ row.is_active ? 'Active' : 'Archived' }}</span
                    ></template
                ><template #cell-actions="{ row }"
                    ><MembershipPlanActions :plan="row" :can-manage="canManage"
                /></template>
            </RecordList>
            <p
                v-if="!plans.data.some((p) => p.kind === section.key)"
                class="py-4 text-center text-sm text-muted-foreground"
            >
                No {{ section.title.toLowerCase() }} on this page.
            </p>
        </section>
        <section
            v-if="tab === 'discounts'"
            class="space-y-3 rounded-xl border bg-card p-4"
        >
            <header class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="flex items-center gap-3 font-semibold">
                    <Percent class="size-5 text-primary" />Discounts
                </h2>
                <Button
                    v-if="canManage"
                    variant="outline"
                    size="sm"
                    @click="editDiscount()"
                    ><Plus class="size-4" />Add discount</Button
                >
            </header>
            <RecordList
                :rows="discountRules"
                row-key="id"
                caption="Member discounts"
                :columns="[
                    { key: 'name', label: 'Type' },
                    { key: 'value', label: 'Discount' },
                    { key: 'rule', label: 'Eligibility' },
                    { key: 'status', label: 'Status' },
                    ...(canManage
                        ? [{ key: 'actions', label: 'Actions' }]
                        : []),
                ]"
                ><template #cell-name="{ row }">{{ row.name }}</template
                ><template #cell-unit="{ row }">{{
                    row.unit === 'peso' ? 'Peso' : 'Percentage'
                }}</template
                ><template #cell-value="{ row }">{{
                    row.unit === 'peso'
                        ? price(row.value)
                        : row.value / 100 + '%'
                }}</template
                ><template #cell-rule="{ row }">{{
                    row.rule_type === 'statutory'
                        ? 'Statutory · verification required'
                        : row.requires_verification
                          ? 'Verification required'
                          : 'Owner promotion'
                }}</template
                ><template #cell-status="{ row }">{{
                    row.is_active ? 'Active' : 'Archived'
                }}</template
                ><template #cell-actions="{ row }"
                    ><Button
                        variant="ghost"
                        size="sm"
                        @click="editDiscount(row)"
                        >Edit</Button
                    ></template
                ></RecordList
            >
            <p
                v-if="!discountRules.length"
                class="text-sm text-muted-foreground"
            >
                Regular members have no category discount. Add Student, PWD,
                Senior or a custom discount when needed.
            </p>
            <p class="text-xs text-muted-foreground">
                PWD/Senior statutory rules use verified eligibility and your
                confirmed VAT profile. They are compared with promotions, never
                stacked. Keep supporting documents offline.
            </p>
        </section>
        <nav
            v-if="tab !== 'discounts'"
            aria-label="Catalog pagination"
            class="flex flex-wrap items-center justify-between gap-3"
        >
            <p class="text-xs text-muted-foreground">
                {{ plans.from ?? 0 }}–{{ plans.to ?? 0 }} of
                {{ plans.total }} offerings
            </p>
            <div class="flex gap-2">
                <Button v-if="plans.prev_page_url" variant="outline" as-child
                    ><Link :href="plans.prev_page_url" preserve-scroll
                        >Previous</Link
                    ></Button
                ><Button v-if="plans.next_page_url" variant="outline" as-child
                    ><Link :href="plans.next_page_url" preserve-scroll
                        >Next</Link
                    ></Button
                >
            </div>
        </nav>
    </div>
    <Dialog v-model:open="discountOpen"
        ><DialogContent class="max-h-[90dvh] overflow-y-auto"
            ><DialogHeader
                ><DialogTitle
                    >{{ discountId ? 'Edit' : 'Add' }} discount</DialogTitle
                ><DialogDescription
                    >Set this gym’s category rule. PWD/Senior retain statutory
                    terms.</DialogDescription
                ></DialogHeader
            >
            <form class="space-y-4" @submit.prevent="saveDiscount">
                <label class="block space-y-1 text-sm"
                    >Name<Input
                        v-model="discount.name"
                        required
                        maxlength="100" /></label
                ><label class="block space-y-1 text-sm"
                    >Member type<NativeSelect
                        aria-label="Member type"
                        v-model="discount.category"
                        ><option
                            v-for="type in [
                                'regular',
                                'student',
                                'pwd',
                                'senior',
                                'others',
                            ]"
                            :key="type"
                            :value="type"
                        >
                            {{
                                type === 'pwd'
                                    ? 'PWD'
                                    : type[0].toUpperCase() + type.slice(1)
                            }}
                        </option></NativeSelect
                    ></label
                >
                <div
                    v-if="
                        !['pwd', 'senior', 'regular'].includes(
                            discount.category,
                        )
                    "
                    class="grid grid-cols-2 gap-3"
                >
                    <label class="space-y-1 text-sm"
                        >Discount unit<NativeSelect
                            aria-label="Discount unit"
                            v-model="discount.unit"
                            ><option value="percentage">Percentage</option>
                            <option value="peso">Peso</option></NativeSelect
                        ></label
                    ><label class="space-y-1 text-sm"
                        >Value<Input
                            v-model="discount.input"
                            inputmode="decimal"
                            required
                    /></label>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    {{
                        discount.category === 'regular'
                            ? 'Regular: no category discount.'
                            : 'Statutory: 20% of the VAT-excluded eligible service price, after verification.'
                    }}
                </p>
                <fieldset
                    v-if="!['pwd', 'senior'].includes(discount.category)"
                    class="space-y-2"
                >
                    <legend class="text-sm font-medium">
                        Eligible offerings
                    </legend>
                    <label
                        v-for="s in sections"
                        :key="s.key"
                        class="flex items-center gap-2 text-sm"
                        ><input
                            v-model="discount.product_kinds"
                            type="checkbox"
                            :value="s.key"
                        />{{ s.title }}</label
                    ><label class="flex items-center gap-2 text-sm"
                        ><input
                            v-model="discount.requires_verification"
                            type="checkbox"
                        />Verify eligibility</label
                    >
                </fieldset>
                <fieldset class="space-y-2">
                    <legend class="mb-2 text-sm font-medium">
                        Specific offerings (optional)
                    </legend>
                    <p class="text-xs text-muted-foreground">
                        Leave unselected for all eligible offering types above.
                    </p>
                    <label
                        v-for="p in discountOfferings.filter((p) =>
                            (['pwd', 'senior'].includes(discount.category)
                                ? ['subscription', 'package']
                                : discount.product_kinds
                            ).includes(p.kind),
                        )"
                        :key="p.id"
                        class="flex items-center gap-2 text-sm"
                        ><input
                            v-model="discount.plan_ids"
                            type="checkbox"
                            :value="p.id"
                        />{{ p.name }}</label
                    >
                </fieldset>
                <div class="grid grid-cols-2 gap-3">
                    <label class="text-sm"
                        >Effective from<Input
                            v-model="discount.effective_from"
                            type="date" /></label
                    ><label class="text-sm"
                        >Effective through<Input
                            v-model="discount.effective_through"
                            type="date"
                    /></label>
                </div>
                <label class="flex items-center gap-2 text-sm"
                    ><input
                        v-model="discount.is_active"
                        type="checkbox"
                    />Available for new purchases</label
                >
                <div role="alert">
                    <InputError
                        v-for="(error, key) in discount.errors"
                        :key="key"
                        :message="error"
                    />
                </div>
                <Button :disabled="discount.processing">Save discount</Button>
            </form></DialogContent
        ></Dialog
    >
</template>
