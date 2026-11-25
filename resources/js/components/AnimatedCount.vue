<script setup lang="ts">
import { useMediaQuery } from '@vueuse/core';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps<{ value: number }>();
const reducedMotion = useMediaQuery('(prefers-reduced-motion: reduce)');
const displayed = ref(props.value);
let frame: number | null = null;
let mounted = false;

function stop() {
    if (frame !== null) cancelAnimationFrame(frame);
    frame = null;
}

function animate() {
    stop();
    const target = props.value;
    if (reducedMotion.value || target === 0) {
        displayed.value = target;
        return;
    }
    displayed.value = 0;
    const started = performance.now();
    const tick = (now: number) => {
        const progress = Math.min((now - started) / 900, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        displayed.value = Math.round(target * eased);
        if (progress < 1) frame = requestAnimationFrame(tick);
        else {
            displayed.value = target;
            frame = null;
        }
    };
    frame = requestAnimationFrame(tick);
}

onMounted(() => {
    mounted = true;
    animate();
});
watch([() => props.value, reducedMotion], () => {
    if (mounted) animate();
});
onBeforeUnmount(stop);
</script>

<template>
    <span aria-hidden="true">{{ displayed.toLocaleString('en-PH') }}</span>
    <span class="sr-only">{{ value.toLocaleString('en-PH') }}</span>
</template>
