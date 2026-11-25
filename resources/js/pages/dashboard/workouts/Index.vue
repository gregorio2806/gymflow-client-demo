<script setup lang="ts">
import RecordList from '@/components/RecordList.vue';
import { Head, Link } from '@inertiajs/vue3';
import WorkoutRoutineController from '@/actions/App/Http/Controllers/Dashboard/WorkoutRoutineController';
import { Button } from '@/components/ui/button';
defineProps<{
    routines: {
        data: { id: number; name: string; branch: string; revision: number }[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    canCreate: boolean;
}>();
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Workouts', href: WorkoutRoutineController.index() },
        ],
    },
});
</script>
<template>
    <Head title="Workouts" />
    <div class="space-y-5 p-4 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">Workout routines</h1>
            <Button v-if="canCreate" as-child
                ><Link :href="WorkoutRoutineController.create()"
                    >Create routine</Link
                ></Button
            >
        </div>
        <p class="text-sm text-muted-foreground">
            Build routines by day, then assign a saved version to members in the
            same branch.
        </p>
        <p
            v-if="!routines.data.length"
            class="rounded-lg border p-6 text-muted-foreground"
        >
            No routines yet. Create your first routine to get started.
        </p>
        <RecordList
            :rows="routines.data"
            :columns="[
                { key: 'name', label: 'Routine' },
                { key: 'branch', label: 'Branch' },
                { key: 'revision', label: 'Version' },
                { key: 'actions', label: 'Actions' },
            ]"
            caption="Workout routines"
            v-if="routines.data.length"
            row-key="id"
            ><template #cell-name="{ row: routine }"
                ><p class="font-medium">{{ routine.name }}</p></template
            ><template #cell-actions="{ row: routine }"
                ><Button variant="outline" as-child
                    ><Link
                        :href="WorkoutRoutineController.edit(routine.id)"
                        :aria-label="'Edit / assign ' + routine.name"
                        >Edit / assign</Link
                    ></Button
                ></template
            ></RecordList
        >
        <nav aria-label="Routine pages" class="flex gap-3">
            <Button v-if="routines.prev_page_url" variant="outline" as-child
                ><Link :href="routines.prev_page_url">Previous</Link></Button
            ><Button v-if="routines.next_page_url" variant="outline" as-child
                ><Link :href="routines.next_page_url">Next</Link></Button
            >
        </nav>
    </div>
</template>
