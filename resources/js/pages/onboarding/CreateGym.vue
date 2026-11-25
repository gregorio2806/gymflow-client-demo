<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/gyms';

defineProps<{
    plan: { label: string; monthlyPrice: number };
    gymName: string | null;
}>();

defineOptions({
    layout: {
        title: 'Create your gym',
        description:
            "Set up your gym to start managing members. You'll be its owner.",
    },
});
</script>

<template>
    <Head title="Create your gym" />

    <Form
        v-bind="store.form()"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="name">Gym name (required)</Label>
                <Input
                    id="name"
                    type="text"
                    required
                    v-focus
                    maxlength="100"
                    autocomplete="organization"
                    name="name"
                    :default-value="gymName ?? ''"
                    placeholder="e.g. DM Fitness"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="phone">Contact number</Label>
                <Input
                    id="phone"
                    type="tel"
                    maxlength="30"
                    autocomplete="tel"
                    name="phone"
                    placeholder="e.g. 09171234567"
                />
                <InputError :message="errors.phone" />
            </div>

            <div class="grid gap-2">
                <Label for="address">Address</Label>
                <Input
                    id="address"
                    type="text"
                    maxlength="255"
                    autocomplete="street-address"
                    name="address"
                    placeholder="Street, city"
                />
                <InputError :message="errors.address" />
            </div>

            <div class="rounded-lg bg-muted px-4 py-3 text-sm">
                <span class="font-medium">Plan:</span> {{ plan.label }} — ₱{{
                    plan.monthlyPrice.toLocaleString('en-PH')
                }}/month
                <span class="block text-muted-foreground"
                    >You won't be charged now.</span
                >
            </div>

            <Button
                type="submit"
                class="mt-2 w-full"
                :disabled="processing"
                data-test="create-gym-button"
            >
                <Spinner v-if="processing" />
                Create gym
            </Button>
        </div>
    </Form>
</template>
