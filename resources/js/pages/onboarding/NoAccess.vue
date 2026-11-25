<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { logout } from '@/routes';
import { create as requestAccess } from '@/routes/access-requests';

defineProps<{
    accessEmail: string;
}>();

defineOptions({
    layout: {
        title: 'You don’t have access to a gym yet',
    },
});
</script>

<template>
    <Head title="No gym access" />

    <div class="space-y-6 text-center">
        <p class="text-muted-foreground">
            If you work at a gym, ask the owner to invite you. If you own a gym,
            send us a request and we'll email you a link to set it up.
        </p>
        <div class="grid gap-3">
            <Button as-child class="w-full">
                <a :href="requestAccess().url">Request access</a>
            </Button>
            <p class="text-sm text-muted-foreground">
                Or email
                <a
                    :href="`mailto:${accessEmail}`"
                    class="underline underline-offset-4"
                    >{{ accessEmail }}</a
                >
            </p>
            <Button as-child variant="outline" class="w-full">
                <Link :href="logout()" method="post" as="button">Log out</Link>
            </Button>
        </div>
    </div>
</template>
