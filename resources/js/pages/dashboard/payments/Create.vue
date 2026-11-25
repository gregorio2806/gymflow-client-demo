<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import MemberSubscriptionController from '@/actions/App/Http/Controllers/Dashboard/MemberSubscriptionController';
import MemberPaymentForm from '@/components/members/MemberPaymentForm.vue';
import { Button } from '@/components/ui/button';
import type { PaymentFormData } from '@/types/member-profile';

defineProps<PaymentFormData>();
defineOptions({ layout: { breadcrumbs: [{ title: 'Apply payment' }] } });
</script>
<template>
    <Head title="Apply payment" />
    <div class="max-w-3xl space-y-6 px-4 py-6 sm:px-6">
        <h1 class="text-xl font-semibold">Apply payment</h1>
        <p>{{ member.name }} · Apply a payment to an existing purchase.</p>
        <Button variant="outline" as-child
            ><Link :href="MemberSubscriptionController.index(member.id)"
                >Back to member</Link
            ></Button
        >
        <MemberPaymentForm
            :member="member"
            :can-record="canRecord"
            :subscriptions="subscriptions"
            :purchases="purchases"
        />
    </div>
</template>
