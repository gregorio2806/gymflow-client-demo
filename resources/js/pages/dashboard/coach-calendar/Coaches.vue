<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Eye } from '@lucide/vue';
import RecordList from '@/components/RecordList.vue';
import { Button } from '@/components/ui/button';
import { index as calendar, roster } from '@/routes/coach-calendar';

defineProps<{
    coaches: {
        data: { id: number; name: string }[];
        total: number;
        from: number | null;
        to: number | null;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}>();
defineOptions({
    layout: { breadcrumbs: [{ title: 'Coach calendar', href: roster() }] },
});
</script>

<template>
    <Head title="Coach calendar" />
    <div class="space-y-5 p-4 sm:p-6">
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">Coach calendar</h1>
                <p class="text-sm text-muted-foreground">
                    Choose a coach to view and manage their calendar.
                </p>
            </div>
        </header>

        <section class="space-y-3" aria-label="Coach list">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm text-muted-foreground">Active coaches</p>
                <p v-if="coaches.total" class="text-sm text-muted-foreground">
                    Showing {{ coaches.from }}–{{ coaches.to }} of
                    {{ coaches.total }} coaches
                </p>
            </div>
            <RecordList
                :rows="coaches.data"
                :columns="[
                    { key: 'name', label: 'Coach' },
                    { key: 'actions', label: 'Actions' },
                ]"
                caption="Active coaches"
                row-key="id"
                empty="No active coaches found."
            >
                <template #cell-name="{ row }">
                    <p class="font-medium">{{ row.name }}</p>
                </template>
                <template #cell-actions="{ row }">
                    <div class="flex justify-end">
                        <Button variant="outline" size="sm" as-child>
                            <Link
                                :href="
                                    calendar({
                                        query: { coach_id: row.id },
                                    })
                                "
                            >
                                <Eye class="size-4" />View
                            </Link>
                        </Button>
                    </div>
                </template>
            </RecordList>
            <nav
                v-if="coaches.prev_page_url || coaches.next_page_url"
                class="flex justify-between"
                aria-label="Coach list pagination"
            >
                <Button v-if="coaches.prev_page_url" variant="outline" as-child>
                    <Link :href="coaches.prev_page_url" preserve-scroll
                        >Previous</Link
                    >
                </Button>
                <span v-else />
                <Button v-if="coaches.next_page_url" variant="outline" as-child>
                    <Link :href="coaches.next_page_url" preserve-scroll
                        >Next</Link
                    >
                </Button>
            </nav>
        </section>
    </div>
</template>
