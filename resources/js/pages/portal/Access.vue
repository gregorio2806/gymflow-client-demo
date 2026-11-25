<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { jsonRequest } from '@/lib/json-request';
import { portalUrl } from '@/lib/portal-route';
import { resolve, login } from '@/routes/portal';
const props = defineProps<{ gymName: string; gymSlug: string }>();
const busy = ref(true);
const error = ref('');
onMounted(async () => {
    let token = window.location.hash.slice(1);
    window.history.replaceState(null, '', window.location.pathname);
    try {
        if (!/^[a-zA-Z0-9]{64}$/.test(token))
            throw new Error(
                'This portal link is invalid. Ask the gym for a new link.',
            );
        const data = await jsonRequest<{ redirect: string }>(
            portalUrl(resolve(props.gymSlug)),
            { token },
        );
        token = '';
        window.location.replace(data.redirect);
    } catch (exception) {
        error.value =
            exception instanceof Error
                ? exception.message
                : 'Could not open this link. Ask the gym for help.';
        busy.value = false;
        token = '';
    }
});
</script>
<template>
    <Head title="Verify member portal"
        ><meta name="robots" content="noindex, nofollow"
    /></Head>
    <div class="max-w-xl space-y-5">
        <h1 class="text-xl font-semibold">Open your member portal</h1>
        <p class="text-sm text-muted-foreground">
            Sign in before viewing your membership details.
        </p>
        <Spinner v-if="busy" aria-label="Opening portal" />
        <p v-if="error" role="alert" class="text-sm text-destructive">
            {{ error }}
        </p>
        <Button v-if="!busy" as-child variant="outline"
            ><a :href="portalUrl(login(gymSlug))"
                >Go to member sign-in</a
            ></Button
        >
    </div>
</template>
