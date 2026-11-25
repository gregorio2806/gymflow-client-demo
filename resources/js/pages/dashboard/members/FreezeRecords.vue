<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import RecordList from '@/components/RecordList.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index } from '@/routes/freeze-records';
import { index as members, show } from '@/routes/members';
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Members', href: members() },
            { title: 'Freeze records', href: index() },
        ],
    },
});
const props = defineProps<{
    member: { id: number; name: string } | null;
    search: string;
    records: {
        data: {
            id: number;
            memberId: number;
            member: string;
            plan: string;
            action: string;
            status: string;
            validThrough: string;
            createdAt: string;
            createdBy: string;
            reason: string;
        }[];
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}>();
const search = ref(props.search);
</script>
<template>
    <Head title="Freeze records" />
    <div class="space-y-4 p-4 sm:p-6">
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">Freeze records</h1>
                <p v-if="member" class="mt-1 text-sm font-medium">
                    {{ member.name }}
                </p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Membership freezes and resumptions, with the account and
                    time recorded.
                </p>
            </div>
            <Button v-if="member" variant="outline" as-child
                ><Link :href="show(member.id, { query: { tab: 'membership' } })"
                    >Freeze / Resume</Link
                ></Button
            >
            <Button v-else variant="outline" as-child
                ><Link :href="members()">View members</Link></Button
            >
        </header>
        <p class="text-sm text-muted-foreground">
            Freezing pauses gym access and keeps the expiry date unchanged. Open
            a member’s Membership section to freeze or resume a subscription.
        </p>
        <form
            class="flex flex-wrap gap-2"
            @submit.prevent="
                router.get(
                    index().url,
                    { search, member_id: member?.id },
                    { preserveState: true, preserveScroll: true },
                )
            "
        >
            <Input
                v-model="search"
                aria-label="Search freeze records"
                placeholder="Member name or ID…"
                maxlength="100"
                class="w-full sm:w-72"
            />
            <Button variant="outline" type="submit">Search</Button>
        </form>
        <RecordList
            :rows="records.data"
            row-key="id"
            caption="Member freeze records"
            wide
            empty="No freeze records yet."
            :columns="[
                { key: 'member', label: 'Member' },
                { key: 'plan', label: 'Subscription' },
                { key: 'action', label: 'Action' },
                { key: 'status', label: 'Current status' },
                { key: 'validThrough', label: 'Valid through' },
                { key: 'actions', label: 'Actions' },
            ]"
        >
            <template #cell-member="{ row }"
                ><p class="font-medium">{{ row.member }}</p></template
            >
            <template #cell-plan="{ row }"
                >{{ row.plan }}
                <p v-if="row.reason" class="mt-1 text-xs text-muted-foreground">
                    {{ row.reason }}
                </p></template
            >
            <template #cell-actions="{ row }"
                ><Button variant="outline" as-child
                    ><Link
                        :href="
                            show(row.memberId, { query: { tab: 'membership' } })
                        "
                        >Membership</Link
                    ></Button
                ></template
            >
        </RecordList>
        <nav
            class="flex flex-wrap items-center gap-3"
            aria-label="Freeze record pages"
        >
            <p class="mr-auto text-xs text-muted-foreground">
                {{ records.total }} records
            </p>
            <Button v-if="records.prev_page_url" variant="outline" as-child
                ><Link :href="records.prev_page_url" preserve-scroll
                    >Previous</Link
                ></Button
            >
            <Button v-if="records.next_page_url" variant="outline" as-child
                ><Link :href="records.next_page_url" preserve-scroll
                    >Next</Link
                ></Button
            >
        </nav>
    </div>
</template>
