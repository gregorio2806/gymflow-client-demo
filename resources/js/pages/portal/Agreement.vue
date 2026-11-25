<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import AccountAgreement from '@/components/AccountAgreement.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { LegalBundle } from '@/types/legal';
import { portalUrl } from '@/lib/portal-route';
import { acceptAgreement, logout } from '@/routes/portal';
defineProps<{ gymName: string; gymSlug: string; legal: LegalBundle }>();
</script>
<template>
    <Head title="Account agreement" />
    <div class="max-w-xl space-y-6">
        <h1 class="text-xl font-semibold">Review your account terms</h1>
        <p>
            Review the terms before using your member portal. Your gym’s privacy
            notice also applies to membership records; ask the front desk or
            check the gym website.
        </p>
        <Form
            :action="portalUrl(acceptAgreement(gymSlug))"
            method="post"
            v-slot="{ errors, processing }"
            class="space-y-5"
        >
            <AccountAgreement
                :legal="legal"
                :error="errors.terms_accepted"
                :version-error="errors.policy_version"
            />
            <Button :disabled="processing"
                ><Spinner v-if="processing" /><span
                    :class="processing ? 'sr-only' : ''"
                    >Agree and continue</span
                ></Button
            >
        </Form>
        <Form :action="portalUrl(logout(gymSlug))" method="post"
            ><Button variant="outline">Sign out</Button></Form
        >
    </div>
</template>
