<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import Controller from '@/actions/App/Http/Controllers/Dashboard/MembershipPlanController';
import ChangeLog from '@/components/ChangeLog.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import InputError from '@/components/InputError.vue';
import { ArrowLeft } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Offering, OfferingKind } from '@/types/pricing';
const props = defineProps<{
    plan:
        | (Offering & {
              price_php: string;
              inclusions: string | null;
              createdAt: string;
              createdBy: string;
              updatedAt: string | null;
              updatedBy: string | null;
          })
        | null;
    kind: OfferingKind;
    readOnly: boolean;
    canManage: boolean;
    history: {
        data: {
            id: number;
            action: string;
            at: string;
            by: string;
            details: string[];
        }[];
        prev_page_url: string | null;
        next_page_url: string | null;
    } | null;
    purchaseBranches: { id: number; name: string }[];
    offerings: Offering[];
    coaches: { id: number; name: string }[];
}>();
defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Plans & Pricing', href: Controller.index() }],
    },
});
const title = computed(() =>
    props.kind === 'subscription'
        ? 'gym subscription'
        : props.kind === 'benefit'
          ? 'membership plan'
          : 'training package',
);
const historyEmptyMessage = computed(() =>
    props.plan
        ? `Created: ${props.plan.createdAt} · ${props.plan.createdBy}. No change history recorded.`
        : 'No change history recorded.',
);
const c = props.plan?.configuration;
const form = useForm({
    kind: props.kind,
    name: props.plan?.name ?? '',
    duration_amount:
        props.plan?.duration_amount ?? (props.kind === 'benefit' ? 12 : 30),
    duration_unit:
        props.plan?.duration_unit ??
        (props.kind === 'benefit' ? 'months' : 'days'),
    price_php: props.plan?.price_php ?? '',
    inclusions: props.plan?.inclusions ?? '',
    branch_ids: props.plan?.branch_ids ?? [],
    configuration:
        props.kind === 'subscription'
            ? {}
            : props.kind === 'benefit'
              ? {
                    discount_unit: c?.discount_unit ?? 'percentage',
                    discount_input: c?.discount_input ?? '0',
                    eligible_plan_ids: c?.eligible_plan_ids ?? [],
                    applies_to_packages: c?.applies_to_packages ?? false,
                    applies_to_renewals: c?.applies_to_renewals ?? true,
                    allow_renewal: c?.allow_renewal ?? true,
                    deferred_start_allowed: c?.deferred_start_allowed ?? true,
                }
              : {
                    duration_mode:
                        c?.duration_mode ??
                        (c?.unlimited_validity ? 'sessions' : 'days'),
                    sessions: c?.sessions ?? 3,
                    coach_ids: c?.coach_ids ?? [],
                },
});
function save() {
    if (props.plan) form.patch(Controller.update(props.plan.id).url);
    else form.post(Controller.store().url);
}
</script>
<template>
    <Head :title="(plan ? (readOnly ? 'View ' : 'Edit ') : 'Add ') + title" />
    <div class="space-y-5 p-4 sm:p-6">
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">
                    {{ plan ? (readOnly ? 'View' : 'Edit') : 'Add' }}
                    {{ title }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    Future purchases use these terms. Existing purchases keep
                    their agreed prices and dates.
                </p>
            </div>
            <div class="flex flex-wrap justify-end gap-2">
                <Button
                    v-if="plan && readOnly && canManage"
                    variant="outline"
                    as-child
                    ><Link :href="Controller.edit(plan.id)">Edit</Link></Button
                >
                <Button v-if="readOnly" variant="outline" as-child
                    ><Link :href="Controller.index({ query: { tab: kind } })"
                        ><ArrowLeft class="size-4" />Back</Link
                    ></Button
                >
            </div>
        </header>
        <div
            class="grid items-start gap-5"
            :class="
                plan ? 'lg:grid-cols-[minmax(0,2fr)_minmax(18rem,1fr)]' : ''
            "
        >
            <form
                class="w-full max-w-3xl min-w-0 space-y-4 rounded-xl border bg-card p-4 sm:p-5"
                @submit.prevent="save"
            >
                <fieldset
                    :disabled="form.processing || readOnly"
                    class="grid gap-4 sm:grid-cols-2"
                >
                    <label class="space-y-1 text-sm sm:col-span-2"
                        >Name<Input
                            v-model="form.name"
                            required
                            maxlength="255" /><InputError
                            :message="form.errors.name"
                    /></label>
                    <label class="space-y-1 text-sm"
                        >Price (PHP)<Input
                            v-model="form.price_php"
                            inputmode="decimal"
                            required /><InputError
                            :message="form.errors.price_php"
                    /></label>
                    <div
                        v-if="kind !== 'package'"
                        class="grid grid-cols-2 gap-2"
                    >
                        <label class="space-y-1 text-sm"
                            >Duration<Input
                                v-model="form.duration_amount"
                                type="number"
                                min="1"
                                :max="
                                    form.duration_unit === 'months' ? 120 : 3650
                                "
                                required /></label
                        ><label class="space-y-1 text-sm"
                            >Unit<NativeSelect
                                aria-label="Unit"
                                v-model="form.duration_unit"
                                ><option value="days">Days</option>
                                <option value="months">
                                    Calendar months
                                </option></NativeSelect
                            ></label
                        >
                    </div>
                    <label class="space-y-1 text-sm sm:col-span-2"
                        >Description (optional)<textarea
                            v-model="form.inclusions"
                            rows="3"
                            maxlength="2000"
                            class="w-full rounded-md border bg-transparent px-3 py-2"
                        />
                    </label>
                    <template v-if="kind === 'benefit'"
                        ><label class="space-y-1 text-sm"
                            >Subscription discount<NativeSelect
                                aria-label="Subscription discount"
                                v-model="form.configuration.discount_unit"
                                ><option value="percentage">Percentage</option>
                                <option value="peso">Peso</option></NativeSelect
                            ></label
                        ><label class="space-y-1 text-sm"
                            >Value<Input
                                v-model="form.configuration.discount_input"
                                inputmode="decimal"
                                required
                        /></label>
                        <fieldset class="space-y-2 sm:col-span-2">
                            <legend class="mb-2 text-sm font-medium">
                                Eligible subscriptions · leave unselected for
                                all
                            </legend>
                            <label
                                v-for="p in offerings.filter(
                                    (p) => p.kind === 'subscription',
                                )"
                                :key="p.id"
                                class="flex items-center gap-2 text-sm"
                                ><input
                                    v-model="
                                        form.configuration.eligible_plan_ids
                                    "
                                    type="checkbox"
                                    :value="p.id"
                                />{{ p.name }}</label
                            >
                        </fieldset>
                        <label class="flex items-center gap-2 text-sm"
                            ><input
                                v-model="form.configuration.applies_to_packages"
                                type="checkbox"
                            />Also applies to training packages</label
                        ><label class="flex items-center gap-2 text-sm"
                            ><input
                                v-model="form.configuration.applies_to_renewals"
                                type="checkbox"
                            />Applies to subscription renewals</label
                        ><label class="flex items-center gap-2 text-sm"
                            ><input
                                v-model="form.configuration.allow_renewal"
                                type="checkbox"
                            />Allow benefit renewal</label
                        ><label class="flex items-center gap-2 text-sm"
                            ><input
                                v-model="
                                    form.configuration.deferred_start_allowed
                                "
                                type="checkbox"
                            />Allow a later start date</label
                        >
                        <p class="text-xs text-muted-foreground sm:col-span-2">
                            The membership fee is charged separately. Its own
                            benefit never discounts that fee.
                        </p></template
                    >
                    <template v-if="kind === 'package'"
                        ><label class="space-y-1 text-sm"
                            >Validity<NativeSelect
                                aria-label="Training package validity"
                                v-model="form.configuration.duration_mode"
                                ><option value="days">Day duration</option>
                                <option value="sessions">Per session</option>
                            </NativeSelect></label
                        ><label
                            v-if="form.configuration.duration_mode === 'days'"
                            class="space-y-1 text-sm"
                            >Days<Input
                                v-model="form.duration_amount"
                                type="number"
                                min="1"
                                max="3650"
                                required /></label
                        ><label class="space-y-1 text-sm"
                            >Sessions<Input
                                v-model="form.configuration.sessions"
                                type="number"
                                min="1"
                                max="10000"
                                required
                        /></label>
                        <fieldset class="space-y-2 sm:col-span-2">
                            <legend class="mb-2 text-sm font-medium">
                                Eligible coaches · leave unselected for all
                            </legend>
                            <label
                                v-for="coach in coaches"
                                :key="coach.id"
                                class="flex items-center gap-2 text-sm"
                                ><input
                                    v-model="form.configuration.coach_ids"
                                    type="checkbox"
                                    :value="coach.id"
                                />{{ coach.name }}</label
                            >
                        </fieldset>
                        <p class="text-xs text-muted-foreground sm:col-span-2">
                            One session is used when its linked appointment is
                            completed. Cancelled/no-show appointments do not use
                            sessions. Packages cannot be transferred.
                        </p></template
                    >
                    <fieldset class="space-y-2 sm:col-span-2">
                        <legend class="mb-2 text-sm font-medium">
                            Eligible branches · leave unselected for all
                        </legend>
                        <label
                            v-for="branch in purchaseBranches"
                            :key="branch.id"
                            class="flex items-center gap-2 text-sm"
                            ><input
                                v-model="form.branch_ids"
                                type="checkbox"
                                :value="branch.id"
                            />{{ branch.name }}</label
                        >
                    </fieldset>
                </fieldset>
                <div role="alert">
                    <InputError
                        v-for="(error, key) in form.errors"
                        :key="key"
                        :message="error"
                    />
                </div>
                <div v-if="!readOnly" class="flex gap-2">
                    <Button :disabled="form.processing">Save {{ title }}</Button
                    ><Button variant="outline" as-child
                        ><Link
                            :href="Controller.index({ query: { tab: kind } })"
                            >Cancel</Link
                        ></Button
                    >
                </div>
            </form>
            <ChangeLog
                v-if="plan && history"
                :history="history"
                title="History log"
                :subtitle="`${plan.name} · Manila time · Latest first`"
                :empty-message="historyEmptyMessage"
                content-id="membership-plan-change-log-content"
            />
        </div>
    </div>
</template>
