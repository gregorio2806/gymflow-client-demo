<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PaymentOverviewController from '@/actions/App/Http/Controllers/Dashboard/PaymentOverviewController';
import PaymentRecordCard from '@/components/PaymentRecordCard.vue';
import type { PaymentRecord } from '@/components/PaymentRecordCard.vue';
import { Button } from '@/components/ui/button';
defineProps<{ record: PaymentRecord }>();
defineOptions({ layout: { breadcrumbs: [{ title: 'Payment record' }] } });
function printRecord() {
    window.print();
}
</script>

<template>
    <Head title="Payment record" />
    <div class="max-w-3xl space-y-6 px-4 py-6 sm:px-6">
        <h1 class="text-xl font-semibold">Payment record</h1>
        <div class="flex flex-wrap gap-3">
            <Button @click="printRecord">Print payment record</Button
            ><Button variant="outline" as-child
                ><Link :href="PaymentOverviewController()"
                    >Back to payments</Link
                ></Button
            >
        </div>
        <PaymentRecordCard :record="record" />
        <Teleport to="body"
            ><section id="payment-record-print" class="hidden">
                <PaymentRecordCard :record="record" /></section
        ></Teleport>
    </div>
</template>

<style>
@media print {
    body:has(> #payment-record-print) > *:not(#payment-record-print) {
        display: none !important;
    }
    body:has(> #payment-record-print) {
        background: white !important;
        color: black !important;
    }
    #payment-record-print {
        display: block !important;
        width: 100%;
        max-width: 170mm;
        margin: 0 auto;
    }
    #payment-record-print article {
        background: white !important;
        color: black !important;
        border: 1px solid black;
        break-inside: avoid;
    }
}
</style>
