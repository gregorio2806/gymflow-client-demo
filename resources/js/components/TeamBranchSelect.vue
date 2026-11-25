<script setup lang="ts">
import { ref, watch } from 'vue';

const props = defineProps<{
    branches: { id: number; name: string }[];
    initialIds: number[];
    role: string;
}>();
const selected = ref([...props.initialIds]);
watch(
    () => props.role,
    (role) => {
        if (role === 'branch-manager')
            selected.value = selected.value.slice(0, 1);
    },
);
function toggle(id: number, checked: boolean) {
    selected.value =
        props.role === 'branch-manager'
            ? [id]
            : checked
              ? [...selected.value, id]
              : selected.value.filter((value) => value !== id);
}
</script>

<template>
    <fieldset class="space-y-2">
        <legend class="text-sm font-medium">
            {{ role === 'branch-manager' ? 'Branch' : 'Branches' }}
        </legend>
        <p class="text-xs text-muted-foreground">
            {{
                role === 'branch-manager'
                    ? 'Choose one branch.'
                    : 'Select every branch this person works at.'
            }}
        </p>
        <div
            class="grid max-h-40 gap-1 overflow-y-auto rounded-md border p-2 sm:grid-cols-2"
        >
            <label
                v-for="branch in branches"
                :key="`${role}-${branch.id}`"
                class="flex cursor-pointer items-center gap-2 rounded px-2 py-2 text-sm hover:bg-muted"
            >
                <input
                    :type="role === 'branch-manager' ? 'radio' : 'checkbox'"
                    name="branch_ids[]"
                    :value="branch.id"
                    :checked="selected.includes(branch.id)"
                    class="size-4 accent-primary"
                    @change="
                        toggle(
                            branch.id,
                            ($event.target as HTMLInputElement).checked,
                        )
                    "
                />
                {{ branch.name }}
            </label>
        </div>
        <input
            v-if="!selected.length"
            type="hidden"
            name="branch_ids[]"
            value=""
        />
    </fieldset>
</template>
