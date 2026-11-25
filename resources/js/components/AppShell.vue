<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import NavigationLoader from '@/components/NavigationLoader.vue';
import { SidebarProvider } from '@/components/ui/sidebar';
import type { AppVariant } from '@/types';

type Props = {
    variant?: AppVariant;
};

withDefaults(defineProps<Props>(), {
    variant: 'sidebar',
});

const isOpen = usePage().props.sidebarOpen;
</script>

<template>
    <div
        v-if="variant === 'header'"
        data-app-shell
        class="flex min-h-screen w-full flex-col"
    >
        <slot />
    </div>
    <SidebarProvider v-else data-app-shell :default-open="isOpen">
        <slot />
    </SidebarProvider>
    <NavigationLoader />
</template>
