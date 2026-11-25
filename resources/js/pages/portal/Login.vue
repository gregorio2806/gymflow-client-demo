<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { portalUrl } from '@/lib/portal-route';
import { authenticate } from '@/routes/portal';
import { nextTick } from 'vue';
const props = defineProps<{ gymName: string; gymSlug: string }>();
const form = useForm({ login: '', password: '' });
function signIn() {
    form.post(portalUrl(authenticate(props.gymSlug)), {
        onFinish: () => form.reset('password'),
        onError: async () => {
            await nextTick();
            document
                .getElementById(
                    form.errors.login ? 'portal-login' : 'portal-password',
                )
                ?.focus();
        },
    });
}
</script>
<template>
    <Head title="Member sign-in"
        ><meta name="robots" content="noindex, nofollow"
    /></Head>
    <div class="max-w-xl space-y-6">
        <h1 class="text-xl font-semibold">Member sign-in</h1>
        <p class="text-sm text-muted-foreground">
            Use the member login and password your gym gave you. Ask the front
            desk if you need a password reset.
        </p>
        <form class="space-y-5" @submit.prevent="signIn">
            <div class="grid gap-2">
                <Label for="portal-login">Member login</Label
                ><Input
                    id="portal-login"
                    v-model="form.login"
                    autocomplete="username"
                    required
                    maxlength="255"
                    autocapitalize="none"
                    :spellcheck="false"
                    :aria-invalid="!!form.errors.login"
                    :aria-describedby="
                        form.errors.login ? 'portal-login-error' : undefined
                    "
                /><InputError
                    id="portal-login-error"
                    :message="form.errors.login"
                />
            </div>
            <div class="grid gap-2">
                <Label for="portal-password">Password</Label
                ><PasswordInput
                    id="portal-password"
                    v-model="form.password"
                    autocomplete="current-password"
                    required
                    maxlength="200"
                    :aria-invalid="!!form.errors.password"
                    :aria-describedby="
                        form.errors.password
                            ? 'portal-password-error'
                            : undefined
                    "
                /><InputError
                    id="portal-password-error"
                    :message="form.errors.password"
                />
            </div>
            <Button class="w-full sm:w-auto" :disabled="form.processing"
                ><Spinner v-if="form.processing" />Sign in</Button
            >
        </form>
    </div>
</template>
