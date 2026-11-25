<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronDown, ChevronUp, History } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';

type HistoryPage = {
    data: {
        id: number;
        action: string;
        at: string;
        by: string;
        details: string[];
    }[];
    prev_page_url: string | null;
    next_page_url: string | null;
};

withDefaults(
    defineProps<{
        history: HistoryPage | null;
        title?: string;
        subtitle: string;
        emptyMessage?: string;
        ariaLabel?: string;
        contentId?: string;
    }>(),
    {
        title: 'Change log',
        emptyMessage: 'No change history recorded.',
        ariaLabel: 'Change history',
        contentId: 'change-log-content',
    },
);
const open = ref(true);
</script>

<template>
    <aside
        v-if="history"
        :aria-label="ariaLabel"
        class="min-w-0 space-y-4 rounded-xl border bg-card p-4 sm:p-5"
    >
        <header class="flex items-center justify-between gap-2">
            <h2 class="flex items-center gap-2 font-semibold">
                <History class="size-4" />{{ title }}
            </h2>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                :aria-expanded="open"
                :aria-controls="contentId"
                :aria-label="open ? `Hide ${title}` : `Show ${title}`"
                :title="open ? `Hide ${title}` : `Show ${title}`"
                @click="open = !open"
            >
                <ChevronUp v-if="open" class="size-4" aria-hidden="true" />
                <ChevronDown v-else class="size-4" aria-hidden="true" />
            </Button>
        </header>
        <div v-show="open" :id="contentId" class="space-y-4">
            <p class="text-xs text-muted-foreground">{{ subtitle }}</p>
            <p
                v-if="!history.data.length"
                class="text-sm text-muted-foreground"
            >
                {{ emptyMessage }}
            </p>
            <ol v-else class="ml-1 space-y-5 border-l pl-4">
                <li
                    v-for="entry in history.data"
                    :key="entry.id"
                    class="relative space-y-1 break-words"
                >
                    <span
                        aria-hidden="true"
                        class="absolute top-1.5 -left-5 size-2 rounded-full bg-primary"
                    />
                    <p class="text-sm font-medium">{{ entry.action }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{ entry.at }}
                    </p>
                    <p class="text-sm">{{ entry.by }}</p>
                    <ul
                        v-if="entry.details.length"
                        class="space-y-1 pt-1 text-xs text-muted-foreground"
                    >
                        <li
                            v-for="(detail, index) in entry.details"
                            :key="index"
                        >
                            {{ detail }}
                        </li>
                    </ul>
                </li>
            </ol>
            <nav
                v-if="history.prev_page_url || history.next_page_url"
                aria-label="Change history pages"
                class="flex gap-2"
            >
                <Button
                    v-if="history.prev_page_url"
                    variant="outline"
                    size="sm"
                    as-child
                >
                    <Link :href="history.prev_page_url" preserve-scroll
                        >Newer</Link
                    >
                </Button>
                <Button
                    v-if="history.next_page_url"
                    variant="outline"
                    size="sm"
                    as-child
                >
                    <Link :href="history.next_page_url" preserve-scroll
                        >Older</Link
                    >
                </Button>
            </nav>
        </div>
    </aside>
</template>
