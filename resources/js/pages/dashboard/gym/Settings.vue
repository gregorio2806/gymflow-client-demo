<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import GymSettingsController from '@/actions/App/Http/Controllers/Dashboard/GymSettingsController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { edit } from '@/routes/gym/settings';

defineProps<{
    gym: {
        name: string;
        email: string | null;
        phone: string | null;
        address: string | null;
        timezone: string;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Gym settings', href: edit() }],
    },
});
</script>

<template>
    <Head title="Gym settings" />

    <div class="px-4 py-6 sm:px-6">
        <h1 class="sr-only">Gym settings</h1>

        <Heading
            title="Gym settings"
            description="Your gym's name and contact details. Members see these on your gym website."
        />

        <Form
            v-bind="GymSettingsController.update.form()"
            v-slot="{ errors, processing }"
            class="max-w-xl space-y-6"
        >
            <div class="grid gap-2">
                <Label for="name">Gym name</Label>
                <Input
                    id="name"
                    name="name"
                    :default-value="gym.name"
                    required
                    maxlength="100"
                    autocomplete="organization"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Contact email (optional)</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    :default-value="gym.email ?? ''"
                    maxlength="255"
                    autocomplete="email"
                    placeholder="e.g. hello@yourgym.ph"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="phone">Contact number (optional)</Label>
                <Input
                    id="phone"
                    type="tel"
                    name="phone"
                    :default-value="gym.phone ?? ''"
                    maxlength="30"
                    autocomplete="tel"
                    placeholder="e.g. 09171234567"
                />
                <InputError :message="errors.phone" />
            </div>

            <div class="grid gap-2">
                <Label for="address">Address (optional)</Label>
                <Input
                    id="address"
                    name="address"
                    :default-value="gym.address ?? ''"
                    maxlength="255"
                    autocomplete="street-address"
                    placeholder="Street, city"
                />
                <InputError :message="errors.address" />
            </div>

            <div class="grid gap-1">
                <p class="text-sm font-medium">Timezone</p>
                <p>Philippine time ({{ gym.timezone }})</p>
                <p class="text-sm text-muted-foreground">
                    Membership expirations and attendance use Philippine time.
                </p>
            </div>

            <Button
                type="submit"
                :disabled="processing"
                data-test="save-gym-settings-button"
            >
                <Spinner v-if="processing" />
                {{ processing ? 'Saving…' : 'Save changes' }}
            </Button>
        </Form>
    </div>
</template>
