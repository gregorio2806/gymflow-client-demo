<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/platform/gyms';
import { edit as editFeatures } from '@/routes/platform/gyms/features';

defineProps<{
    gyms: {
        data: {
            id: number;
            name: string;
            slug: string;
            status: string;
            plan: string;
            subscriptionStatus: string;
        }[];
        prev_page_url: string | null;
        next_page_url: string | null;
        total: number;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Platform: gyms', href: index() }],
    },
});
</script>

<template>
    <Head title="Platform: gyms" />

    <div class="px-4 py-6 sm:px-6">
        <h1 class="sr-only">Platform: gyms</h1>

        <Heading
            title="Gyms"
            :description="`${gyms.total} gym${gyms.total === 1 ? '' : 's'} on the platform. Choose a gym to switch its features on or off.`"
        />

        <p
            v-if="gyms.data.length === 0"
            class="rounded-xl border p-6 text-muted-foreground"
        >
            No gyms yet. They appear here when an owner creates one.
        </p>

        <ul v-else class="max-w-3xl divide-y divide-border rounded-xl border">
            <li v-for="gym in gyms.data" :key="gym.id">
                <Link
                    :href="editFeatures(gym.id)"
                    class="flex min-h-14 items-center justify-between gap-3 p-4 hover:bg-accent/50"
                >
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ gym.name }}</p>
                        <p class="truncate text-sm text-muted-foreground">
                            {{ gym.slug }}
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <Badge variant="outline">
                            {{ gym.plan }} ·
                            {{ gym.subscriptionStatus.replace('_', ' ') }}
                        </Badge>
                        <Badge
                            :variant="
                                gym.status === 'active'
                                    ? 'secondary'
                                    : 'destructive'
                            "
                        >
                            {{ gym.status }}
                        </Badge>
                        <ChevronRight class="size-5 text-muted-foreground" />
                    </div>
                </Link>
            </li>
        </ul>

        <nav
            v-if="gyms.prev_page_url || gyms.next_page_url"
            aria-label="Pagination"
            class="mt-4 flex max-w-3xl justify-between"
        >
            <Button v-if="gyms.prev_page_url" variant="outline" as-child>
                <Link :href="gyms.prev_page_url">Previous</Link>
            </Button>
            <span v-else />
            <Button v-if="gyms.next_page_url" variant="outline" as-child>
                <Link :href="gyms.next_page_url">Next</Link>
            </Button>
        </nav>
    </div>
</template>
