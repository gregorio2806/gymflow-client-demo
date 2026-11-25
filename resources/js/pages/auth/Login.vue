<script setup lang="ts">
import { create as requestAccess } from '@/routes/access-requests';
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import { nextTick } from 'vue';

async function focusError(errors: Record<string, string>) {
    await nextTick();
    document.getElementById(errors.email ? 'email' : 'password')?.focus();
}

defineOptions({
    layout: {
        title: 'Log in to your account',
        description: 'Enter your email and password below to log in',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
    developmentAccounts?: {
        password: string;
        accounts: { role: string; email: string; portalUrl?: string }[];
    };
}>();
</script>

<template>
    <Head title="Log in" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <PasskeyVerify />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
        @error="focusError"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    v-focus
                    :aria-invalid="!!errors.email"
                    :aria-describedby="errors.email ? 'email-error' : undefined"
                    autocomplete="email"
                    placeholder="email@example.com"
                />
                <InputError id="email-error" :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password">Password</Label>
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-sm"
                    >
                        Forgot your password?
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :aria-invalid="!!errors.password"
                    :aria-describedby="
                        errors.password ? 'password-error' : undefined
                    "
                    autocomplete="current-password"
                    placeholder="Password"
                />
                <InputError id="password-error" :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label for="remember" class="flex items-center space-x-3">
                    <Checkbox id="remember" name="remember" />
                    <span>Remember me</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-4 w-full"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Log in
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            Own a gym and don't have an account?
            <a
                :href="requestAccess().url"
                class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                >Request access</a
            >
        </div>
    </Form>
    <aside
        v-if="developmentAccounts"
        class="mt-6 space-y-3 border-t border-border pt-4 text-sm"
        aria-labelledby="development-accounts-title"
    >
        <h2 id="development-accounts-title" class="font-semibold">
            Development accounts
        </h2>
        <p class="text-muted-foreground">
            Password for all accounts:
            <code class="text-foreground select-all">{{
                developmentAccounts.password
            }}</code>
        </p>
        <dl class="space-y-3">
            <div
                v-for="account in developmentAccounts.accounts"
                :key="account.email"
                class="space-y-1"
            >
                <dt class="text-muted-foreground">{{ account.role }}</dt>
                <dd>
                    <code class="break-all select-all">{{
                        account.email
                    }}</code>
                </dd>
                <dd v-if="account.portalUrl">
                    <a
                        :href="account.portalUrl"
                        class="inline-flex min-h-11 items-center underline"
                        >Open member portal</a
                    >
                </dd>
            </div>
        </dl>
    </aside>
</template>
