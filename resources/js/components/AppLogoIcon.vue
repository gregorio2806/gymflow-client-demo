<script setup lang="ts">
import { Dumbbell } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
defineOptions({ inheritAttrs: false });
defineProps<{ className?: HTMLAttributes['class'] }>();
</script>

<template>
    <Dumbbell :class="className" v-bind="$attrs" aria-hidden="true" />
</template>
