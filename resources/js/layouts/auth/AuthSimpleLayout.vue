<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AuthBrand from '@/components/AuthBrand.vue';
import NavigationLoader from '@/components/NavigationLoader.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <!-- Guest pages always use the dark brand look (black / white / red) -->
    <div
        class="dark flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 text-foreground md:p-10"
    >
        <a
            href="#main-content"
            class="sr-only z-50 rounded-md bg-primary px-4 py-3 text-primary-foreground focus:not-sr-only focus:fixed focus:top-2 focus:left-2"
            >Skip to main content</a
        >
        <main id="main-content" tabindex="-1" class="w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-4">
                    <Link
                        :href="home()"
                        aria-label="Home"
                        class="flex flex-col items-center gap-2 font-medium"
                    >
                        <div
                            class="mb-1 flex h-9 w-9 items-center justify-center rounded-md"
                        >
                            <AuthBrand />
                        </div>
                        <span class="sr-only">{{ title }}</span>
                    </Link>
                    <div class="space-y-2 text-center">
                        <h1 class="text-xl font-medium">{{ title }}</h1>
                        <p class="text-center text-sm text-muted-foreground">
                            {{ description }}
                        </p>
                    </div>
                </div>
                <slot />
            </div>
        </main>
        <NavigationLoader />
    </div>
</template>
