<script setup lang="ts">
import { formatDisplayDate } from '@/lib/date-format';
defineProps<{
    createdAt?: string | null;
    createdBy?: string | null;
    updatedAt?: string | null;
    updatedBy?: string | null;
}>();
</script>
<template>
    <div class="mt-2 space-y-1 text-xs text-muted-foreground">
        <p v-if="createdAt">
            Created: {{ formatDisplayDate(createdAt) }} (Manila)
        </p>
        <p>Created by: {{ createdBy || 'Not recorded' }}</p>
        <template v-if="updatedAt">
            <p>Last updated: {{ formatDisplayDate(updatedAt) }} (Manila)</p>
            <p>Updated by: {{ updatedBy || 'Not recorded' }}</p>
        </template>
    </div>
</template>
