<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AccountAgreement from '@/components/AccountAgreement.vue';
import type { LegalBundle } from '@/types/legal';
import { nextTick } from 'vue';

async function focusError() {
    await nextTick();
    document
        .querySelector<HTMLElement>('#main-content [aria-invalid="true"]')
        ?.focus();
}

/**
 * "Create your account" for people who received an invitation email
 * (public sign-up is closed). The email is fixed to the invited address.
 */
defineProps<{
    email: string;
    action: { action: string; method: 'post' };
    submitLabel: string;
    legal: LegalBundle;
}>();
</script>

<template>
    <Form
        v-bind="action"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="grid gap-5 text-left"
        @error="focusError"
    >
        <p class="text-sm text-muted-foreground">
            Your name, both password fields and the account agreement are
            required.
        </p>
        <div class="grid gap-2">
            <Label for="invited-email">Email</Label>
            <Input
                id="invited-email"
                type="email"
                :default-value="email"
                readonly
                :aria-invalid="!!errors.email"
                :aria-describedby="
                    errors.email ? 'invited-email-error' : undefined
                "
            />
            <InputError id="invited-email-error" :message="errors.email" />
        </div>

        <div class="grid gap-2">
            <Label for="name">Your name</Label>
            <Input
                id="name"
                name="name"
                required
                autocomplete="name"
                placeholder="Full name"
                :aria-invalid="!!errors.name"
                :aria-describedby="
                    errors.name ? 'signup-name-error' : undefined
                "
            />
            <InputError id="signup-name-error" :message="errors.name" />
        </div>

        <div class="grid gap-2">
            <Label for="password">Password</Label>
            <PasswordInput
                id="password"
                name="password"
                required
                autocomplete="new-password"
                placeholder="Password"
                :aria-invalid="!!errors.password"
                :aria-describedby="
                    errors.password ? 'signup-password-error' : undefined
                "
            />
            <InputError id="signup-password-error" :message="errors.password" />
        </div>

        <div class="grid gap-2">
            <Label for="password_confirmation">Confirm password</Label>
            <PasswordInput
                id="password_confirmation"
                name="password_confirmation"
                required
                autocomplete="new-password"
                placeholder="Confirm password"
                :aria-invalid="!!errors.password_confirmation"
                :aria-describedby="
                    errors.password_confirmation
                        ? 'signup-confirmation-error'
                        : undefined
                "
            />
            <InputError
                id="signup-confirmation-error"
                :message="errors.password_confirmation"
            />
        </div>

        <AccountAgreement
            :legal="legal"
            :error="errors.terms_accepted"
            :version-error="errors.policy_version"
        />
        <Button
            type="submit"
            class="w-full"
            :disabled="processing"
            :aria-busy="processing"
        >
            <Spinner v-if="processing" />
            {{ submitLabel }}
        </Button>
    </Form>
</template>
