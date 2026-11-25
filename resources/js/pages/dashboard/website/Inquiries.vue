<script setup lang="ts">
import RecordList from '@/components/RecordList.vue';
import { formatDisplayDate } from '@/lib/date-format';
import { Head, Link } from '@inertiajs/vue3';
import WebsiteController from '@/actions/App/Http/Controllers/Dashboard/WebsiteController';
import WebsiteInquiryController from '@/actions/App/Http/Controllers/Dashboard/WebsiteInquiryController';
import { Button } from '@/components/ui/button';
defineProps<{
    retentionDays: number;
    inquiries: {
        data: {
            name: string;
            email: string;
            message: string;
            receivedAt: string;
        }[];
        links: { url: string | null; label: string; active: boolean }[];
        current_page: number;
        last_page: number;
    };
}>();
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Gym website', href: WebsiteController.edit() },
            { title: 'Inquiries', href: WebsiteInquiryController.index() },
        ],
    },
});
</script>
<template>
    <Head title="Website inquiries" />
    <div class="max-w-4xl space-y-5 p-4 sm:p-6">
        <header class="space-y-3">
            <h1 class="text-2xl font-semibold">Website inquiries</h1>
            <p class="text-sm text-muted-foreground">
                Visitors consented to a response to their inquiry. Contact them
                using your usual email service. Records are removed after
                {{ retentionDays }} days.
            </p>
            <Button as-child variant="outline"
                ><Link :href="WebsiteController.edit()"
                    >Back to gym website</Link
                ></Button
            >
        </header>
        <p v-if="!inquiries.data.length" class="rounded-xl border p-6">
            No website inquiries yet.
        </p>
        <RecordList
            :rows="inquiries.data"
            :columns="[
                { key: 'name', label: 'Visitor', width: '20%' },
                { key: 'email', label: 'Email', width: '25%' },
                { key: 'receivedAt', label: 'Received (Manila)', width: '20%' },
                { key: 'message', label: 'Message', width: '35%' },
            ]"
            caption="Website inquiries"
            v-if="inquiries.data.length"
            ><template #cell-name="{ row: item }"
                ><p class="font-medium">{{ item.name }}</p></template
            ><template #cell-email="{ row: item }"
                ><span class="break-all select-all">{{
                    item.email
                }}</span></template
            ><template #cell-receivedAt="{ row: item }">{{
                formatDisplayDate(item.receivedAt)
            }}</template
            ><template #cell-message="{ row: item }"
                ><p class="whitespace-pre-line">{{ item.message }}</p></template
            ></RecordList
        >
        <nav
            v-if="inquiries.last_page > 1"
            aria-label="Inquiry pagination"
            class="flex flex-wrap items-center gap-2"
        >
            <template v-for="(link, index) in inquiries.links" :key="index"
                ><Button
                    v-if="link.url"
                    as-child
                    :variant="link.active ? 'secondary' : 'outline'"
                    ><Link
                        :href="link.url"
                        :aria-current="link.active ? 'page' : undefined"
                        ><span v-html="link.label" /></Link></Button
            ></template>
        </nav>
    </div>
</template>
