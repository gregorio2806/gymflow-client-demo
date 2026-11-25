<script setup lang="ts">
import AttendanceCheckIn from '@/components/AttendanceCheckIn.vue';
import { Head } from '@inertiajs/vue3';
import CheckInController from '@/actions/App/Http/Controllers/Dashboard/CheckInController';
defineProps<{
    branches: { id: number; name: string }[];
    defaultBranchId: number | null;
}>();
defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Check-in', href: CheckInController.index() }],
    },
});
</script>
<template>
    <Head title="Check-in" />
    <div class="p-4 sm:p-6">
        <AttendanceCheckIn
            :branches="branches"
            :default-branch-id="defaultBranchId"
        />
    </div>
</template>
