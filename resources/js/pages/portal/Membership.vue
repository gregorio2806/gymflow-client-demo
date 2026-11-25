<script setup lang="ts">
import { formatDisplayDate } from '@/lib/date-format';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { portalUrl } from '@/lib/portal-route';
import { logout } from '@/routes/portal';
const props = defineProps<{
    gymName: string;
    gymSlug: string;
    memberName: string;
    membership: {
        planName: string;
        status: string;
        startsOn: string;
        validThrough: string;
    } | null;
    renewalInstructions: string;
}>();
const busy = ref(false);
function signOut() {
    busy.value = true;
    router.post(
        portalUrl(logout(props.gymSlug)),
        {},
        {
            onFinish: () => {
                busy.value = false;
            },
        },
    );
}
</script>
<template>
    <Head title="My membership"
        ><meta name="robots" content="noindex, nofollow"
    /></Head>
    <div class="space-y-6">
        <header class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold">My membership</h1>
                <p class="mt-2 break-words">{{ memberName }}</p>
            </div>
            <Button variant="outline" :disabled="busy" @click="signOut"
                ><Spinner v-if="busy" />Sign out</Button
            >
        </header>
        <dl
            v-if="membership"
            class="grid gap-6 rounded-xl border bg-card p-6 sm:grid-cols-2"
        >
            <div
                v-for="[label, value] in [
                    ['Plan', membership.planName],
                    ['Status', membership.status],
                    ['Starts on', formatDisplayDate(membership.startsOn)],
                    [
                        'Valid through',
                        formatDisplayDate(membership.validThrough),
                    ],
                ]"
                :key="label"
            >
                <dt class="text-sm text-muted-foreground">{{ label }}</dt>
                <dd class="mt-2 font-semibold break-words">{{ value }}</dd>
            </div>
        </dl>
        <p v-else class="rounded-xl border p-6">
            No membership has been assigned yet. Ask the front desk to check
            your membership.
        </p>
        <section class="space-y-3 rounded-xl border bg-card p-6">
            <h2 class="text-lg font-semibold">Renew your membership</h2>
            <p class="text-sm leading-6">{{ renewalInstructions }}</p>
            <p class="text-sm text-muted-foreground">
                Dates are shown in Manila time.
            </p>
        </section>
    </div>
</template>
