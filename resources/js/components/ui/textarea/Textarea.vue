<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { useVModel } from '@vueuse/core';
import { cn } from '@/lib/utils';
const props = defineProps<{ defaultValue?: string; modelValue?: string; class?: HTMLAttributes['class'] }>();
const emits = defineEmits<{ (event: 'update:modelValue', value: string): void }>();
const value = useVModel(props, 'modelValue', emits, { passive: true, defaultValue: props.defaultValue });
</script>
<template>
    <textarea v-model="value" :class="cn('border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 min-h-24 w-full min-w-0 rounded-md border bg-transparent px-3 py-2 text-base shadow-xs outline-none focus-visible:ring-[3px] disabled:opacity-50 md:text-sm', props.class)" />
</template>
