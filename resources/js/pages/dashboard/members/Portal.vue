<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { onBeforeUnmount, ref } from 'vue';
import MemberPortalController from '@/actions/App/Http/Controllers/Dashboard/MemberPortalController';
import MemberController from '@/actions/App/Http/Controllers/Dashboard/MemberController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';
import { jsonRequest } from '@/lib/json-request';
const props = defineProps<{
    member: { id: number; name: string };
    login: string | null;
    hasLogin: boolean;
    hasQr: boolean;
    hasStatusCode: boolean;
    canManage: boolean;
    loginUrl: string;
}>();
defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Members', href: MemberController.index() }],
    },
});
const hasLogin = ref(props.hasLogin);
const hasQr = ref(props.hasQr);
const hasStatusCode = ref(props.hasStatusCode);
const statusCode = ref('');
const login = ref(props.login ?? '');
const credentials = ref<{ login: string; password: string } | null>(null);
const payload = ref('');
const image = ref('');
const busy = ref(false);
const error = ref('');
const notice = ref('');
const confirmation = ref('');
const abort = new AbortController();
let disposed = false;
async function manage(operation: string) {
    if (busy.value) return;
    busy.value = true;
    error.value = '';
    credentials.value = null;
    statusCode.value = '';
    payload.value = '';
    image.value = '';
    try {
        const result = await jsonRequest<{
            credentials: { login: string; password: string } | null;
            payload: string | null;
            hasLogin: boolean;
            statusCode: string | null;
            hasStatusCode: boolean;
            hasQr: boolean;
        }>(
            MemberPortalController.store(props.member.id).url,
            { operation, login: login.value || null },
            abort.signal,
        );
        if (disposed) return;
        hasLogin.value = result.hasLogin;
        hasQr.value = result.hasQr;
        hasStatusCode.value = result.hasStatusCode;
        statusCode.value = result.statusCode ?? '';
        credentials.value = result.credentials;
        payload.value = result.payload ?? '';
        confirmation.value = '';
        if (result.credentials) login.value = result.credentials.login;
        notice.value =
            result.credentials || result.payload || result.statusCode
                ? 'Saved. Copy the credentials or download the QR now; they are only shown in this visit.'
                : 'Portal access updated.';
        if (result.payload) {
            const { toDataURL } = await import('qrcode');
            image.value = await toDataURL(result.payload, {
                width: 360,
                margin: 4,
                errorCorrectionLevel: 'M',
            });
        }
    } catch (exception) {
        if (!disposed)
            error.value =
                exception instanceof Error
                    ? exception.message
                    : 'Could not update portal access. Try again.';
    } finally {
        busy.value = false;
    }
}
onBeforeUnmount(() => {
    disposed = true;
    statusCode.value = '';
    abort.abort();
    credentials.value = null;
    payload.value = '';
    image.value = '';
});
</script>
<template>
    <Head title="Member portal access" />
    <div class="max-w-3xl space-y-6 px-4 py-6 sm:px-6">
        <header class="space-y-2">
            <h1 class="text-xl font-semibold">Member portal access</h1>
            <p class="break-words">{{ member.name }}</p>
            <Button as-child variant="outline"
                ><Link :href="MemberController.show(member.id)"
                    >Back to member</Link
                ></Button
            >
        </header>
        <p class="text-sm text-muted-foreground">
            Give login credentials privately to the member. Portal QR links
            require member sign-in and are separate from attendance cards.
        </p>
        <p v-if="!canManage" class="text-sm">
            This member's branch is read-only. Activate it before changing
            portal access.
        </p>
        <p v-if="error" role="alert" class="text-sm text-destructive">
            {{ error }}
        </p>
        <p v-if="notice" role="status" class="text-sm">{{ notice }}</p>
        <section class="max-w-xl space-y-4 rounded-xl border p-5">
            <h2 class="text-lg font-semibold">Member login</h2>
            <template v-if="!hasLogin"
                ><Label for="new-member-login">Login name</Label
                ><Input
                    id="new-member-login"
                    v-model="login"
                    placeholder="e.g. alex.member"
                    maxlength="255"
                    autocapitalize="none"
                    :spellcheck="false"
                />
                <p class="text-sm text-muted-foreground">
                    Use a unique login for this gym. Letters, numbers, @, dots,
                    and +, - or _ are allowed.
                </p>
                <Button
                    :disabled="!canManage || busy"
                    @click="manage('create-login')"
                    ><Spinner v-if="busy" />Create member login</Button
                ></template
            >
            <template v-else
                ><p class="break-all">Login: {{ login }}</p>
                <div class="flex flex-wrap gap-3">
                    <Button
                        variant="outline"
                        :disabled="!canManage || busy"
                        @click="confirmation = 'reset-password'"
                        >Reset password</Button
                    ><Button
                        variant="outline"
                        :disabled="!canManage || busy"
                        @click="confirmation = 'disable-login'"
                        >Disable portal login</Button
                    >
                </div></template
            >
            <a
                :href="loginUrl"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex min-h-11 items-center underline"
                >Open member sign-in</a
            >
            <div v-if="credentials" class="grid gap-3 rounded-lg border p-4">
                <Label for="issued-login">Copy member login</Label
                ><Input
                    id="issued-login"
                    :model-value="credentials.login"
                    readonly
                    class="select-all"
                /><Label for="issued-password">Copy new password</Label
                ><Input
                    id="issued-password"
                    :model-value="credentials.password"
                    readonly
                    class="select-all"
                />
            </div>
        </section>
        <section class="max-w-xl space-y-4 rounded-xl border p-5">
            <h2 class="text-lg font-semibold">Portal QR link</h2>
            <div class="flex flex-wrap gap-3">
                <Button
                    v-if="!hasQr"
                    :variant="hasLogin ? 'default' : 'outline'"
                    :disabled="!hasLogin || !canManage || busy"
                    @click="manage('generate-qr')"
                    ><Spinner v-if="busy" />Generate portal QR</Button
                ><template v-else
                    ><Button
                        variant="outline"
                        :disabled="!canManage || busy"
                        @click="confirmation = 'reissue-qr'"
                        >Reissue portal QR</Button
                    ><Button
                        variant="outline"
                        :disabled="!canManage || busy"
                        @click="confirmation = 'revoke-qr'"
                        >Revoke portal QR</Button
                    ></template
                >
            </div>
            <p v-if="!hasLogin" class="text-sm text-muted-foreground">
                Create a member login first.
            </p>
            <template v-if="payload"
                ><Label for="issued-link">Copy portal link</Label
                ><Input
                    id="issued-link"
                    :model-value="payload"
                    readonly
                    class="select-all"
                /><img
                    v-if="image"
                    :src="image"
                    alt="Member portal QR"
                    width="360"
                    height="360"
                    class="h-auto max-w-full rounded-lg"
                /><Button v-if="image" as-child variant="outline"
                    ><a :href="image" download="member-portal-qr.png"
                        >Download portal QR</a
                    ></Button
                ></template
            >
        </section>
        <section class="max-w-xl space-y-4 rounded-xl border p-5">
            <h2 class="text-lg font-semibold">Membership check code</h2>
            <p class="text-sm text-muted-foreground">
                Give this code privately to the member. It shows membership
                status without signing in. Issuing a replacement invalidates the
                old code.
            </p>
            <Button
                variant="outline"
                :disabled="!canManage || busy"
                @click="
                    hasStatusCode
                        ? (confirmation = 'issue-status-code')
                        : manage('issue-status-code')
                "
                >{{
                    hasStatusCode ? 'Replace check code' : 'Issue check code'
                }}</Button
            >
            <Button
                v-if="hasStatusCode"
                variant="outline"
                :disabled="!canManage || busy"
                @click="confirmation = 'revoke-status-code'"
                >Revoke check code</Button
            >
            <template v-if="statusCode"
                ><Label for="issued-status-code">Copy check code now</Label
                ><Input
                    id="issued-status-code"
                    :model-value="statusCode"
                    readonly
                    class="select-all"
            /></template>
        </section>
        <Dialog
            :open="!!confirmation"
            @update:open="
                (open) => {
                    if (!open && !busy) confirmation = '';
                }
            "
            ><DialogContent
                ><DialogHeader
                    ><DialogTitle
                        >Update portal access for
                        {{ member.name }}?</DialogTitle
                    ><DialogDescription>{{
                        confirmation.includes('status-code')
                            ? 'The old membership check code will stop working.'
                            : confirmation === 'reset-password'
                              ? 'The member must sign in again with the new password.'
                              : confirmation === 'disable-login'
                                ? 'The member will lose portal access until a login is created again.'
                                : 'The old QR link and sessions verified with that QR will stop working.'
                    }}</DialogDescription></DialogHeader
                ><DialogFooter
                    ><Button
                        variant="outline"
                        :disabled="busy"
                        @click="confirmation = ''"
                        >Cancel</Button
                    ><Button :disabled="busy" @click="manage(confirmation)"
                        ><Spinner v-if="busy" />Confirm update</Button
                    ></DialogFooter
                ></DialogContent
            ></Dialog
        >
    </div>
</template>
