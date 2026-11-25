<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';
import MemberController from '@/actions/App/Http/Controllers/Dashboard/MemberController';
import { History } from '@lucide/vue';
import { index as freezeRecords } from '@/routes/freeze-records';
import MemberCheckout from '@/components/members/MemberCheckout.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { PricingCatalogData } from '@/types/pricing';
const props = defineProps<
    PricingCatalogData & {
        member: {
            id: number;
            name: string;
            mobile: string;
            email: string | null;
            home_branch_id: number;
            membershipStatus: string;
        } | null;
        branches: { id: number; name: string }[];
        canSave: boolean;
        readOnly: boolean;
    }
>();
defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Members', href: MemberController.index() }],
    },
});
const form = useForm({
    name: props.member?.name ?? '',
    mobile: props.member?.mobile ?? '',
    email: props.member?.email ?? '',
    home_branch_id: props.member?.home_branch_id ?? 0,
});
const errorsRef = ref<HTMLElement | null>(null);
function save() {
    if (props.member)
        form.patch(MemberController.update(props.member.id).url, {
            onError: () => {
                nextTick(() => errorsRef.value?.focus());
            },
        });
}
</script>
<template>
    <Head :title="member ? 'Edit member' : 'Add member'" />
    <div class="@container space-y-4 p-4 sm:p-6">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">
                {{ member ? 'Edit member' : 'Add member' }}
            </h1>
            <Button v-if="member" variant="outline" as-child
                ><Link
                    :href="freezeRecords({ query: { member_id: member.id } })"
                    ><History class="size-4" />Freeze records</Link
                ></Button
            >
        </header>
        <MemberCheckout
            v-if="!member"
            :offerings="offerings"
            :discounts="discounts"
            :pricing-settings="pricingSettings"
            :today="today"
            :purchase-branches="purchaseBranches"
            :can-save="canSave"
        />
        <form
            v-else
            class="max-w-2xl space-y-4 rounded-xl border bg-card p-4 sm:p-5"
            @submit.prevent="save"
        >
            <p class="text-sm text-muted-foreground">
                Membership: {{ member.membershipStatus }}
            </p>
            <fieldset
                :disabled="form.processing || readOnly || !canSave"
                class="space-y-4"
            >
                <label class="block space-y-1 text-sm"
                    >Name<Input
                        id="member-name"
                        v-model="form.name"
                        required
                        maxlength="255"
                        :aria-invalid="!!form.errors.name" /></label
                ><label class="block space-y-1 text-sm"
                    >Mobile number (optional)<Input
                        v-model="form.mobile"
                        type="tel"
                        maxlength="20"
                        :aria-invalid="!!form.errors.mobile" /></label
                ><label class="block space-y-1 text-sm"
                    >Email (optional)<Input
                        v-model="form.email"
                        type="email"
                        maxlength="255"
                        :aria-invalid="!!form.errors.email" /></label
                ><label class="block space-y-1 text-sm"
                    >Registration branch<NativeSelect
                        aria-label="Registration branch"
                        v-model="form.home_branch_id"
                        ><option
                            v-for="branch in branches"
                            :key="branch.id"
                            :value="branch.id"
                        >
                            {{ branch.name }}
                        </option></NativeSelect
                    ></label
                >
                <p class="text-xs text-muted-foreground">
                    This records where the member registered. Branch access
                    follows their purchased subscription’s terms.
                </p>
            </fieldset>
            <div
                v-if="Object.keys(form.errors).length"
                ref="errorsRef"
                tabindex="-1"
                role="alert"
            >
                <InputError
                    v-for="(error, key) in form.errors"
                    :key="key"
                    :message="error"
                />
            </div>
            <p v-if="!canSave" class="text-sm text-muted-foreground">
                This branch is read-only.
            </p>
            <Button v-if="!readOnly" :disabled="form.processing || !canSave"
                >Save member</Button
            >
        </form>
    </div>
</template>
