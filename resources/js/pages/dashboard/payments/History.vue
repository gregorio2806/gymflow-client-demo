<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import MemberPaymentHistory from '@/components/members/MemberPaymentHistory.vue';
import type { PaymentHistoryData } from '@/types/member-profile';
defineProps<PaymentHistoryData>();
defineOptions({ layout: { breadcrumbs: [{ title: 'Payment history' }] } });
</script>
<template>
    <Head title="Payment history" />
    <div class="space-y-5 p-4 sm:p-6">
        <header>
            <h1 class="text-2xl font-semibold">Payment history</h1>
            <p class="mt-1 text-sm text-muted-foreground">{{ member.name }}</p>
        </header>
        <MemberPaymentHistory :member="member" :payments="payments" />
    </div>
</template>
