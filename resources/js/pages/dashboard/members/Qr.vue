<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import MemberQrController from '@/actions/App/Http/Controllers/Dashboard/MemberQrController';
import MemberController from '@/actions/App/Http/Controllers/Dashboard/MemberController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { jsonRequest } from '@/lib/json-request';

const props = defineProps<{
    member: { id: number; name: string };
    hasQr: boolean;
    canManage: boolean;
}>();
defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Members', href: MemberController.index() }],
    },
});
const page = usePage();
const hasQr = ref(props.hasQr);
const image = ref('');
const busy = ref(false);
const error = ref('');
const notice = ref('');
const confirmation = ref<'reissue' | 'revoke' | null>(null);
const abort = new AbortController();
let disposed = false;
async function manage(operation: 'generate' | 'view' | 'reissue' | 'revoke') {
    if (busy.value) return;
    busy.value = true;
    error.value = '';
    try {
        const result = await jsonRequest<{
            payload: string | null;
            hasQr: boolean;
        }>(
            MemberQrController.store(props.member.id).url,
            { operation },
            abort.signal,
        );
        if (disposed) return;
        hasQr.value = result.hasQr;
        image.value = '';
        confirmation.value = null;
        notice.value = result.payload
            ? operation === 'view'
                ? 'QR loaded. Print or download the current card.'
                : 'QR saved. Print or download the card now.'
            : 'QR revoked. The old card no longer works.';
        if (result.payload) {
            const { toDataURL } = await import('qrcode');
            image.value = await toDataURL(result.payload, {
                width: 320,
                margin: 4,
                errorCorrectionLevel: 'M',
            });
        }
    } catch (exception) {
        if (!disposed)
            error.value =
                exception instanceof Error
                    ? exception.message
                    : 'Could not save the QR. Try again.';
    } finally {
        busy.value = false;
    }
}
function printCard() {
    window.print();
}
onBeforeUnmount(() => {
    disposed = true;
    abort.abort();
    image.value = '';
});
onMounted(() => {
    if (hasQr.value) manage('view');
});
</script>

<template>
    <Head :title="`${member.name} - Attendance QR`" />
    <div class="space-y-6 px-4 py-6 sm:px-6">
        <header class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold break-words">
                    {{ member.name }}
                </h1>
                <p class="text-muted-foreground">Attendance QR</p>
            </div>
            <Button
                v-if="page.props.features.includes('members')"
                as-child
                variant="outline"
                ><Link :href="MemberController.index()"
                    >Back to members</Link
                ></Button
            >
        </header>
        <p>
            This QR is for staff check-in. Keep the printed card private. It
            does not provide member portal access.
        </p>
        <p v-if="!canManage" class="text-muted-foreground">
            This home branch is read-only. QR changes are unavailable.
        </p>
        <p v-else-if="hasQr && !image" class="text-muted-foreground">
            A QR already exists. View the current card or reissue it to replace
            the previous card.
        </p>
        <p v-else-if="!hasQr" class="text-muted-foreground">
            No usable attendance QR. Generate one to print a member card.
        </p>
        <div v-if="canManage" class="flex flex-wrap gap-3">
            <Button
                v-if="!hasQr"
                :disabled="busy"
                @click="manage('generate')"
                >{{ busy ? 'Saving…' : 'Generate QR' }}</Button
            >
            <Button v-else :disabled="busy" @click="confirmation = 'reissue'"
                >Reissue QR</Button
            >
            <Button
                v-if="hasQr"
                variant="outline"
                :disabled="busy"
                @click="confirmation = 'revoke'"
                >Revoke QR</Button
            >
        </div>
        <p v-if="notice" role="status">{{ notice }}</p>
        <p v-if="error" role="alert" class="text-destructive">{{ error }}</p>
        <template v-if="image">
            <section
                class="max-w-sm rounded-xl border bg-white p-6 text-center text-black"
                aria-label="Printable member card"
            >
                <p class="font-semibold break-words">
                    {{ page.props.currentGym?.name }}
                </p>
                <img
                    :src="image"
                    :alt="`Attendance QR for ${member.name}`"
                    class="mx-auto w-full max-w-80"
                    width="320"
                    height="320"
                />
                <p class="text-lg font-semibold break-words">
                    {{ member.name }}
                </p>
                <p class="text-sm">Member attendance card</p>
            </section>
            <div class="flex flex-wrap gap-3">
                <Button variant="outline" @click="printCard">Print card</Button
                ><Button as-child variant="outline"
                    ><a :href="image" download="attendance-qr.png"
                        >Download QR</a
                    ></Button
                >
            </div>
        </template>
        <Teleport v-if="image" to="body">
            <section
                id="attendance-card-print"
                class="hidden bg-white p-6 text-center text-black"
            >
                <p class="font-semibold break-words">
                    {{ page.props.currentGym?.name }}
                </p>
                <img
                    :src="image"
                    :alt="`Attendance QR for ${member.name}`"
                    class="mx-auto w-full max-w-80"
                    width="320"
                    height="320"
                />
                <p class="text-lg font-semibold break-words">
                    {{ member.name }}
                </p>
                <p class="text-sm">Member attendance card</p>
            </section>
        </Teleport>
        <Dialog
            :open="confirmation !== null"
            @update:open="
                (open) => {
                    if (!open && !busy) confirmation = null;
                }
            "
        >
            <DialogContent
                ><DialogHeader
                    ><DialogTitle
                        >{{ confirmation === 'reissue' ? 'Replace' : 'Revoke' }}
                        {{ member.name }}'s QR?</DialogTitle
                    ><DialogDescription
                        >The previous printed card will stop working.
                        {{
                            confirmation === 'reissue'
                                ? 'Print the new card after saving.'
                                : 'You can generate a new card later.'
                        }}</DialogDescription
                    ></DialogHeader
                >
                <DialogFooter
                    ><Button
                        variant="outline"
                        :disabled="busy"
                        @click="confirmation = null"
                        >Keep current QR</Button
                    ><Button
                        :variant="
                            confirmation === 'revoke'
                                ? 'destructive'
                                : 'default'
                        "
                        :disabled="busy"
                        @click="confirmation && manage(confirmation)"
                        >{{
                            busy
                                ? 'Saving…'
                                : confirmation === 'reissue'
                                  ? 'Replace QR'
                                  : 'Revoke QR'
                        }}</Button
                    ></DialogFooter
                >
            </DialogContent>
        </Dialog>
    </div>
</template>

<style>
@media print {
    body:has(> #attendance-card-print) > *:not(#attendance-card-print) {
        display: none !important;
    }
    body:has(> #attendance-card-print) {
        background: white !important;
    }
    #attendance-card-print {
        display: block !important;
        width: 90mm;
        border: 1px solid #000;
    }
}
</style>
