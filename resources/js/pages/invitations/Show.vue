<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import InvitationController from '@/actions/App/Http/Controllers/InvitationController';
import InvitedSignupForm from '@/components/InvitedSignupForm.vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { home, login, logout } from '@/routes';
import type { LegalBundle } from '@/types/legal';
import AccountAgreement from '@/components/AccountAgreement.vue';

defineProps<{
    status: 'invalid' | 'guest' | 'ready' | 'wrong-account';
    invitation?: {
        gymName: string;
        role: string;
        name: string | null;
        email: string;
    };
    token?: string;
    hasAccount?: boolean;
    legal?: LegalBundle;
}>();

defineOptions({
    layout: {
        title: 'Team invitation',
    },
});
</script>

<template>
    <Head title="Team invitation" />

    <div
        v-if="status === 'invalid' || !invitation"
        class="space-y-6 text-center"
    >
        <p class="text-muted-foreground">
            This invitation link is invalid, expired, or already used. Ask the
            gym owner to send you a new one.
        </p>
        <TextLink :href="home()">Go to the home page</TextLink>
    </div>

    <div v-else class="space-y-6 text-center">
        <p>
            <span class="font-semibold">{{ invitation.gymName }}</span>
            invited you to join their team as
            <span class="font-semibold">{{
                invitation.role.replaceAll('-', ' ')
            }}</span
            >.
        </p>

        <!-- Not logged in: existing account → log in; new person → create account here -->
        <template v-if="status === 'guest' && token && legal">
            <template v-if="hasAccount">
                <p class="text-sm text-muted-foreground">
                    Log in with
                    <span class="font-medium text-foreground">{{
                        invitation.email
                    }}</span>
                    to accept.
                </p>
                <Button as-child class="w-full">
                    <Link :href="login()">Log in to accept</Link>
                </Button>
            </template>
            <InvitedSignupForm
                v-else
                :email="invitation.email"
                :name="invitation.name"
                :action="InvitationController.register.form(token)"
                :submit-label="`Create account and join ${invitation.gymName}`"
                :legal="legal"
            />
        </template>

        <!-- Logged in with the invited email -->
        <Form
            v-else-if="status === 'ready' && token && legal"
            v-bind="InvitationController.accept.form(token)"
            v-slot="{ processing, errors }"
            class="space-y-5"
        >
            <AccountAgreement
                :legal="legal"
                :error="errors.terms_accepted"
                :version-error="errors.policy_version"
            />
            <InputError :message="errors.email" class="mb-4" />
            <Button
                type="submit"
                class="w-full"
                :disabled="processing"
                data-test="accept-invitation-button"
            >
                <Spinner v-if="processing" />
                Join {{ invitation.gymName }}
            </Button>
        </Form>

        <!-- Logged in with a different email -->
        <template v-else>
            <p class="text-sm text-muted-foreground">
                This invitation is for
                <span class="font-medium text-foreground">{{
                    invitation.email
                }}</span
                >, but you're logged in with a different account. Log out and
                sign in with that email to accept.
            </p>
            <Button as-child variant="outline" class="w-full">
                <Link :href="logout()" method="post" as="button">Log out</Link>
            </Button>
        </template>
    </div>
</template>
