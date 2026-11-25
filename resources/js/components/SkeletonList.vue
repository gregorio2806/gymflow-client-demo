<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';

/**
 * Placeholder shaped like a list of rows (avatar + two lines + badge).
 * Use as the fallback of Inertia's <Deferred> for slow-loading lists:
 *
 *   <Deferred data="members">
 *       <template #fallback><SkeletonList :rows="5" /></template>
 *       ...
 *   </Deferred>
 */
withDefaults(defineProps<{ rows?: number }>(), { rows: 5 });
</script>

<template>
    <ul
        class="divide-y divide-border rounded-xl border"
        aria-busy="true"
        aria-label="Loading"
    >
        <li v-for="row in rows" :key="row" class="flex items-center gap-3 p-4">
            <Skeleton class="size-10 shrink-0 rounded-full" />
            <div class="flex-1 space-y-2">
                <Skeleton class="h-4 w-2/5" />
                <Skeleton class="h-3 w-3/5" />
            </div>
            <Skeleton class="h-5 w-14 rounded-full" />
        </li>
    </ul>
</template>
