<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import OwnerInvitationController from '@/actions/App/Http/Controllers/OwnerInvitationController';
import InvitedSignupForm from '@/components/InvitedSignupForm.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { home, login, logout } from '@/routes';
import type { LegalBundle } from '@/types/legal';
import AccountAgreement from '@/components/AccountAgreement.vue';

defineProps<{
    status: 'invalid' | 'guest' | 'ready' | 'wrong-account';
    invitation?: { email: string; plan: string };
    hasAccount?: boolean;
    token?: string;
    legal?: LegalBundle;
}>();

defineOptions({
    layout: {
        title: 'Set up your gym account',
    },
});
</script>

<template>
    <Head title="Set up your gym account" />

    <div
        v-if="status === 'invalid' || !invitation || !token"
        class="space-y-6 text-center"
    >
        <p class="text-muted-foreground">
            This access link is invalid, expired, or already used. Reply to our
            email and we'll send you a new one.
        </p>
        <TextLink :href="home()">Go to the home page</TextLink>
    </div>

    <div v-else class="space-y-6 text-center">
        <p>
            You've been given access on the
            <span class="font-semibold">{{ invitation.plan }}</span> plan.
        </p>

        <!-- New person: create the account here -->
        <template v-if="status === 'guest' && !hasAccount && legal">
            <p class="text-sm text-muted-foreground">
                Set your name and password, then create your gym.
            </p>
            <InvitedSignupForm
                :email="invitation.email"
                :action="OwnerInvitationController.register.form(token)"
                submit-label="Create account"
                :legal="legal"
            />
        </template>

        <!-- Already has an account -->
        <template v-else-if="status === 'guest'">
            <p class="text-sm text-muted-foreground">
                You already have an account with
                <span class="font-medium text-foreground">{{
                    invitation.email
                }}</span
                >. Log in to continue.
            </p>
            <Button as-child class="w-full">
                <Link :href="login()">Log in to continue</Link>
            </Button>
        </template>

        <!-- Logged in with the invited email -->
        <Form
            v-else-if="status === 'ready' && legal"
            v-bind="OwnerInvitationController.accept.form(token)"
            v-slot="{ processing, errors }"
            class="space-y-5"
        >
            <AccountAgreement
                :legal="legal"
                :error="errors.terms_accepted"
                :version-error="errors.policy_version"
            />
            <Button type="submit" class="w-full" :disabled="processing">
                <Spinner v-if="processing" />
                Continue to create your gym
            </Button>
        </Form>

        <!-- Logged in with a different email -->
        <template v-else>
            <p class="text-sm text-muted-foreground">
                This access is for
                <span class="font-medium text-foreground">{{
                    invitation.email
                }}</span
                >, but you're logged in with a different account. Log out and
                open the link again.
            </p>
            <Button as-child variant="outline" class="w-full">
                <Link :href="logout()" method="post" as="button">Log out</Link>
            </Button>
        </template>
    </div>
</template>
