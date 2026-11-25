<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';
import type { LegalBundle } from '@/types/legal';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogScrollContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import InputError from '@/components/InputError.vue';
const props = defineProps<{
    legal: LegalBundle;
    error?: string;
    versionError?: string;
}>();
const open = ref(false);
watch(
    () => [props.error, props.versionError],
    async ([error, versionError]) => {
        if (error || versionError) {
            await nextTick();
            document.getElementById('terms-accepted')?.focus();
        }
    },
);
</script>
<template>
    <div class="space-y-2">
        <input type="hidden" name="policy_version" :value="legal.version" />
        <label
            for="terms-accepted"
            class="flex min-h-11 items-start gap-3 text-left text-sm"
        >
            <input
                id="terms-accepted"
                name="terms_accepted"
                type="checkbox"
                value="1"
                required
                class="mt-1 size-5 shrink-0 accent-primary"
                :aria-invalid="!!(error || versionError)"
                :aria-describedby="
                    error || versionError ? 'agreement-error' : undefined
                "
            />
            <span
                >I agree to the account terms and acknowledge the privacy policy
                (required).</span
            >
        </label>
        <Button type="button" variant="outline" @click="open = true"
            >Read terms and policies</Button
        >
        <InputError id="agreement-error" :message="error || versionError" />
        <Dialog v-model:open="open">
            <DialogScrollContent class="[&>button]:size-11">
                <DialogHeader class="pr-12"
                    ><DialogTitle>Terms and policies</DialogTitle
                    ><DialogDescription
                        >Version {{ legal.label }}. Account agreement does not
                        authorize optional health processing or
                        marketing.</DialogDescription
                    ></DialogHeader
                >
                <p v-if="legal.draft" class="rounded-md border p-3 text-sm">
                    Draft for local review; operator details and commercial
                    terms await publication.
                </p>
                <section
                    v-for="document in legal.documents"
                    :key="document.slug"
                    class="space-y-4 text-left"
                >
                    <h2 class="text-xl font-semibold">{{ document.title }}</h2>
                    <div
                        v-for="section in document.sections"
                        :key="section.title"
                        class="space-y-2"
                    >
                        <h3 class="font-semibold">{{ section.title }}</h3>
                        <p
                            class="text-sm leading-relaxed break-words whitespace-pre-line"
                        >
                            {{ section.text }}
                        </p>
                    </div>
                </section>
                <Button type="button" variant="outline" @click="open = false"
                    >Close policies</Button
                >
            </DialogScrollContent>
        </Dialog>
    </div>
</template>
