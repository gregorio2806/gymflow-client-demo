<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Dumbbell } from '@lucide/vue';
import { onMounted, onUnmounted, ref } from 'vue';

/**
 * Shown only when a page change takes longer than a second (slow connections).
 * The red progress bar at the top already covers short waits. CSS + inline SVG only.
 */
const SHOW_AFTER_MS = 1000;

const visible = ref(false);
let timer: ReturnType<typeof setTimeout> | undefined;
let stopListening: Array<() => void> = [];

onMounted(() => {
    stopListening = [
        router.on('start', () => {
            clearTimeout(timer);
            timer = setTimeout(() => (visible.value = true), SHOW_AFTER_MS);
        }),
        router.on('finish', () => {
            clearTimeout(timer);
            visible.value = false;
        }),
    ];
});

onUnmounted(() => {
    clearTimeout(timer);
    stopListening.forEach((stop) => stop());
});
</script>

<template>
    <Transition
        enter-active-class="transition duration-200 ease-out motion-reduce:transition-none"
        enter-from-class="translate-y-2 opacity-0"
        leave-active-class="transition duration-150 ease-in motion-reduce:transition-none"
        leave-to-class="translate-y-2 opacity-0"
    >
        <div
            v-if="visible"
            role="status"
            class="fixed bottom-6 left-1/2 z-50 flex -translate-x-1/2 items-center rounded-full bg-foreground p-2 text-background shadow-lg"
        >
            <span
                class="flex size-7 items-center justify-center rounded-full bg-primary text-primary-foreground"
            >
                <Dumbbell
                    class="size-4 animate-bounce motion-reduce:animate-none"
                />
            </span>
            <span class="sr-only">Loading</span>
        </div>
    </Transition>
</template>
