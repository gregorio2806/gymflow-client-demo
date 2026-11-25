<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import GymFeatureController from '@/actions/App/Http/Controllers/Platform/GymFeatureController';
import GymSubscriptionController from '@/actions/App/Http/Controllers/Platform/GymSubscriptionController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { index } from '@/routes/platform/gyms';

defineProps<{
    gym: { id: number; name: string; slug: string };
    subscription: {
        plan: string;
        billingCycle: string;
        status: string;
        extraBranches: number;
        currentPeriodEndsAt: string | null;
    };
    plans: { value: string; label: string }[];
    features: {
        key: string;
        label: string;
        active: boolean;
        default: boolean;
        mode: 'default' | 'on' | 'off';
    }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Platform: gyms', href: index() }],
    },
});
</script>

<template>
    <Head :title="`Features — ${gym.name}`" />

    <div class="px-4 py-6 sm:px-6">
        <h1 class="sr-only">Features for {{ gym.name }}</h1>

        <Heading
            :title="gym.name"
            description="Switch modules on or off for this gym. Switched-off modules disappear from their menu and their pages return “not found”. Changes are recorded in the gym's audit log."
        />

        <!-- Plan (set by hand until online billing exists) -->
        <section class="mb-10 max-w-xl">
            <Heading
                variant="small"
                title="Plan"
                description="Set this after the gym pays (e.g. GCash). Changes are recorded in the gym's audit log."
            />
            <Form
                v-bind="GymSubscriptionController.update.form(gym.id)"
                v-slot="{ errors, processing }"
                class="mt-4 grid gap-4 sm:grid-cols-2"
            >
                <div class="grid gap-2">
                    <Label for="plan">Plan</Label>
                    <NativeSelect id="plan" name="plan" required>
                        <option
                            v-for="plan in plans"
                            :key="plan.value"
                            :value="plan.value"
                            :selected="plan.value === subscription.plan"
                        >
                            {{ plan.label }}
                        </option>
                    </NativeSelect>
                    <InputError :message="errors.plan" />
                </div>
                <div class="grid gap-2">
                    <Label for="billing_cycle">Billing</Label>
                    <NativeSelect
                        id="billing_cycle"
                        name="billing_cycle"
                        required
                    >
                        <option
                            value="monthly"
                            :selected="subscription.billingCycle === 'monthly'"
                        >
                            Monthly
                        </option>
                        <option
                            value="yearly"
                            :selected="subscription.billingCycle === 'yearly'"
                        >
                            Yearly (pay 10, get 12)
                        </option>
                    </NativeSelect>
                    <InputError :message="errors.billing_cycle" />
                </div>
                <div class="grid gap-2">
                    <Label for="status">Status</Label>
                    <NativeSelect id="status" name="status" required>
                        <option
                            v-for="status in [
                                'trialing',
                                'active',
                                'past_due',
                                'cancelled',
                            ]"
                            :key="status"
                            :value="status"
                            :selected="status === subscription.status"
                        >
                            {{ status.replace('_', ' ') }}
                        </option>
                    </NativeSelect>
                    <InputError :message="errors.status" />
                </div>
                <div class="grid gap-2">
                    <Label for="current_period_ends_at"
                        >Paid until (optional)</Label
                    >
                    <Input
                        id="current_period_ends_at"
                        type="date"
                        name="current_period_ends_at"
                        :default-value="subscription.currentPeriodEndsAt ?? ''"
                    />
                    <InputError :message="errors.current_period_ends_at" />
                </div>
                <div class="grid gap-2">
                    <Label for="extra_branches"
                        >Extra branches (Multi-Branch only)</Label
                    >
                    <Input
                        id="extra_branches"
                        type="number"
                        name="extra_branches"
                        min="0"
                        max="50"
                        required
                        :default-value="String(subscription.extraBranches)"
                    />
                    <InputError :message="errors.extra_branches" />
                </div>
                <div class="flex items-end">
                    <Button type="submit" :disabled="processing" class="w-full">
                        <Spinner v-if="processing" />
                        {{ processing ? 'Saving…' : 'Save plan' }}
                    </Button>
                </div>
            </Form>
        </section>

        <Heading variant="small" title="Features" />
        <Form
            v-bind="GymFeatureController.update.form(gym.id)"
            v-slot="{ errors, processing }"
            class="max-w-xl space-y-6"
        >
            <fieldset class="min-w-0 divide-y divide-border rounded-xl border">
                <legend class="sr-only">Features</legend>
                <label
                    v-for="feature in features"
                    :key="feature.key"
                    :for="`feature-${feature.key}`"
                    class="grid min-h-14 min-w-0 cursor-pointer grid-cols-1 items-center gap-2 px-4 py-3 hover:bg-accent/50 sm:grid-cols-[minmax(0,1fr)_11rem] sm:gap-4"
                >
                    <span class="min-w-0 font-medium break-words">{{
                        feature.label
                    }}</span>
                    <NativeSelect
                        :id="`feature-${feature.key}`"
                        :name="`overrides[${feature.key}]`"
                        class="max-w-full min-w-0"
                    >
                        <option
                            value="default"
                            :selected="feature.mode === 'default'"
                        >
                            Plan default ({{ feature.default ? 'on' : 'off' }})
                        </option>
                        <option value="on" :selected="feature.mode === 'on'">
                            Always on
                        </option>
                        <option value="off" :selected="feature.mode === 'off'">
                            Always off
                        </option>
                    </NativeSelect>
                </label>
            </fieldset>
            <InputError :message="errors.features" />

            <Button
                type="submit"
                :disabled="processing"
                data-test="save-features-button"
            >
                <Spinner v-if="processing" />
                {{ processing ? 'Saving…' : 'Save features' }}
            </Button>
        </Form>
    </div>
</template>
