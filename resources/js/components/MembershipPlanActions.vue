<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
import { Eye, Pencil } from '@lucide/vue';
import MembershipPlanController from '@/actions/App/Http/Controllers/Dashboard/MembershipPlanController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import type { MembershipPlanRow } from '@/types/membership-plans';
defineProps<{ plan: MembershipPlanRow; canManage: boolean }>();
</script>

<template>
    <div class="flex items-center justify-end gap-2">
        <Form
            v-if="canManage"
            v-bind="MembershipPlanController.activation.form(plan.id)"
            v-slot="{ processing, errors }"
            class="contents"
        >
            <input
                type="hidden"
                name="is_active"
                :value="plan.is_active ? '0' : '1'"
            />
            <button
                type="submit"
                role="switch"
                :aria-checked="plan.is_active"
                :aria-busy="processing"
                :aria-label="`${plan.name}: available for new memberships`"
                :title="plan.is_active ? 'Archive plan' : 'Activate plan'"
                :disabled="processing"
                class="flex h-11 w-14 cursor-pointer items-center justify-center rounded-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-60"
            >
                <span
                    aria-hidden="true"
                    class="relative h-7 w-13 rounded-full transition-colors duration-200 motion-reduce:transition-none"
                    :class="plan.is_active ? 'bg-green-700' : 'bg-red-700'"
                    ><span
                        class="absolute top-1 left-1 size-5 rounded-full bg-white shadow-sm transition-transform duration-200 motion-reduce:transition-none"
                        :class="
                            plan.is_active ? 'translate-x-6' : 'translate-x-0'
                        "
                /></span>
            </button>
            <InputError
                role="alert"
                :message="errors.is_active"
                class="order-last col-span-full break-words"
            />
        </Form>
        <Button variant="ghost" size="icon" as-child
            ><Link
                :href="MembershipPlanController.show(plan.id)"
                :aria-label="`View ${plan.name}`"
                :title="`View ${plan.name}`"
                ><Eye class="size-5" /></Link
        ></Button>
        <Button v-if="canManage" variant="ghost" size="icon" as-child
            ><Link
                :href="MembershipPlanController.edit(plan.id)"
                :aria-label="`Edit ${plan.name}`"
                :title="`Edit ${plan.name}`"
                ><Pencil class="size-5" /></Link
        ></Button>
    </div>
</template>
