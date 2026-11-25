<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import DemoBanner from '@/components/DemoBanner.vue';
import PlanReviewBanner from '@/components/PlanReviewBanner.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <a
        href="#main-content"
        class="sr-only z-50 rounded-md bg-primary px-4 py-3 text-primary-foreground focus:not-sr-only focus:fixed focus:top-2 focus:left-2"
        >Skip to main content</a
    >
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent
            id="main-content"
            tabindex="-1"
            variant="sidebar"
            class="min-w-0 overflow-x-clip"
        >
            <DemoBanner />
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <PlanReviewBanner />
            <slot />
        </AppContent>
        <Toaster />
    </AppShell>
</template>
