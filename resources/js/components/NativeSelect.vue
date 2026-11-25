<script setup lang="ts">
/**
 * Native <select> styled like <Input> (44px). Uses the phone's own picker and adds no JS.
 * Pass <option> elements in the default slot; attributes (id, name, required) fall through.
 */
const model = defineModel<string | number | null>();
</script>

<template>
    <select
        v-if="model !== undefined"
        v-model="model"
        class="h-11 w-full rounded-md border border-input bg-transparent px-3 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30"
    >
        <slot />
    </select>
    <select
        v-else
        class="h-11 w-full rounded-md border border-input bg-transparent px-3 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30"
    >
        <slot />
    </select>
</template>
