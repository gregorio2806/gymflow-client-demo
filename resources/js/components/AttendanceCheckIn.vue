<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    X,
    Search,
    Camera,
    QrCode,
    ChevronRight,
    Upload,
    CheckCircle,
} from '@lucide/vue';
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type { IScannerControls } from '@zxing/browser';
import CheckInController from '@/actions/App/Http/Controllers/Dashboard/CheckInController';
import NativeSelect from '@/components/NativeSelect.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { jsonRequest } from '@/lib/json-request';

const props = defineProps<{
    branches: { id: number; name: string }[];
    defaultBranchId: number | null;
}>();
const emit = defineEmits<{ 'checked-in': [] }>();
const branchId = ref(props.defaultBranchId);
const mode = ref<'search' | 'qr'>('search');
const readingImage = ref(false);
const photoInput = ref<HTMLInputElement | null>(null);
let imageRun = 0;
const search = ref('');
type MemberChoice = {
    id: number;
    name: string;
    is_active: boolean;
    mobileHint: string;
    membershipStatus: string;
    membershipSummary: string | null;
};
const members = ref<MemberChoice[]>([]);
const selectedMembers = ref<MemberChoice[]>([]);
const searchPage = ref(1);
const lastPage = ref(1);
const searching = ref(false);
const searched = ref(false);
const searchError = ref('');
const busy = ref(false);
const error = ref('');
const notice = ref('');
const cameraError = ref('');
const cameraActive = ref(false);
const video = ref<HTMLVideoElement | null>(null);
let controls: IScannerControls | undefined;
let cameraStream: MediaStream | undefined;
let cameraRun = 0;
let disposed = false;
let searchAbort: AbortController | undefined;
const submissionAbort = new AbortController();
let searchTimer: ReturnType<typeof setTimeout> | undefined;

function focusSearch() {
    void nextTick(() => {
        const input = document.getElementById('attendance-search');
        if (input instanceof HTMLInputElement) input.focus();
    });
}
function stopCamera() {
    cameraRun++;
    controls?.stop();
    controls = undefined;
    cameraStream?.getTracks().forEach((track) => track.stop());
    cameraStream = undefined;
    const stream = video.value?.srcObject;
    if (typeof MediaStream !== 'undefined' && stream instanceof MediaStream)
        stream.getTracks().forEach((track) => track.stop());
    if (video.value) video.value.srcObject = null;
    cameraActive.value = false;
}
function canCheckIn(member: MemberChoice): boolean {
    return (
        member.is_active &&
        ['Active', 'Expiring soon'].includes(member.membershipStatus)
    );
}
function selectMember(member: MemberChoice) {
    if (
        busy.value ||
        !canCheckIn(member) ||
        selectedMembers.value.some((choice) => choice.id === member.id)
    )
        return;
    selectedMembers.value.push(member);
    search.value = '';
    void nextTick(() => {
        document.getElementById('attendance-search')?.focus();
    });
}
function changeMode(value: 'search' | 'qr') {
    mode.value = value;
    if (value === 'search') {
        imageRun++;
        readingImage.value = false;
        stopCamera();
    }
}
async function scanPhoto(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';
    if (!file || busy.value || readingImage.value || !branchId.value) return;
    cameraError.value = '';
    if (
        !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) ||
        file.size > 4 * 1024 * 1024
    ) {
        cameraError.value = 'Choose a JPG, PNG or WebP QR image up to 4 MB.';
        return;
    }
    stopCamera();
    const run = ++imageRun;
    readingImage.value = true;
    const url = URL.createObjectURL(file);
    try {
        const image = new Image();
        image.src = url;
        await image.decode();
        if (image.naturalWidth > 4096 || image.naturalHeight > 4096)
            throw new Error('Image too large');
        const { BrowserQRCodeReader } = await import('@zxing/browser');
        const result = await new BrowserQRCodeReader().decodeFromImageElement(
            image,
        );
        if (!disposed && run === imageRun) await checkIn(result.getText());
    } catch {
        if (!disposed && run === imageRun)
            cameraError.value =
                'Could not read an attendance QR. Choose a clear QR image up to 4096 pixels per side, or search for the member.';
    } finally {
        URL.revokeObjectURL(url);
        if (run === imageRun) readingImage.value = false;
    }
}
function removeMember(id: number) {
    selectedMembers.value = selectedMembers.value.filter(
        (member) => member.id !== id,
    );
    focusSearch();
}
async function checkIn(scannedToken?: string) {
    if (busy.value) return;
    if (!scannedToken && !selectedMembers.value.length) {
        error.value = 'Select at least one eligible member first.';
        return;
    }
    if (!branchId.value) {
        error.value = 'Choose the check-in branch.';
        return;
    }
    busy.value = true;
    error.value = '';
    notice.value = '';
    stopCamera();
    const submissions = scannedToken
        ? [{ token: scannedToken.trim() }]
        : selectedMembers.value.map((member) => ({ member_id: member.id }));
    const messages: string[] = [];
    try {
        for (const submission of submissions) {
            const result = await jsonRequest<{
                memberName: string;
                checkedInAt: string;
                duplicate?: boolean;
            }>(
                CheckInController.store().url,
                { branch_id: Number(branchId.value), ...submission },
                submissionAbort.signal,
            );
            if (disposed) return;
            messages.push(
                `${result.memberName}: ${result.duplicate ? 'already checked in' : 'checked in'} at ${result.checkedInAt} (Manila time).`,
            );
            if ('member_id' in submission)
                selectedMembers.value = selectedMembers.value.filter(
                    (member) => member.id !== submission.member_id,
                );
            notice.value = messages.join(' ');
        }
        search.value = '';
    } catch (exception) {
        if (!disposed)
            error.value =
                exception instanceof Error
                    ? exception.message
                    : 'Could not check in. Try again.';
    } finally {
        busy.value = false;
        if (!disposed) {
            if (messages.length) {
                emit('checked-in');
                await findMembers();
            }
            if (mode.value === 'search') focusSearch();
        }
    }
}
async function startCamera() {
    if (cameraActive.value || busy.value || readingImage.value) return;
    cameraError.value = '';
    if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
        cameraError.value =
            'Camera scanning needs HTTPS or localhost. Search for the member instead.';
        return;
    }
    cameraActive.value = true;
    const run = ++cameraRun;
    let stage: 'scanner' | 'device' | 'preview' = 'scanner';
    try {
        const { BrowserQRCodeReader } = await import('@zxing/browser');
        await nextTick();
        if (disposed || run !== cameraRun || !video.value) return;
        stage = 'device';
        let stream: MediaStream;
        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: 'environment' } },
                audio: false,
            });
        } catch (exception) {
            if (
                !(exception instanceof Error) ||
                !['OverconstrainedError', 'NotFoundError'].includes(
                    exception.name,
                )
            )
                throw exception;
            if (disposed || run !== cameraRun) return;
            stream = await navigator.mediaDevices.getUserMedia({
                video: true,
                audio: false,
            });
        }
        if (disposed || run !== cameraRun) {
            stream.getTracks().forEach((track) => track.stop());
            return;
        }
        cameraStream = stream;
        stage = 'preview';
        const reader = new BrowserQRCodeReader(undefined, {
            tryPlayVideoTimeout: 15000,
        });
        const activeControls = await reader.decodeFromStream(
            stream,
            video.value,
            (result, _error, scanControls) => {
                if (result && !busy.value && run === cameraRun) {
                    scanControls.stop();
                    void checkIn(result.getText());
                }
            },
        );
        if (disposed || run !== cameraRun) activeControls.stop();
        else controls = activeControls;
    } catch (exception) {
        if (!disposed && run === cameraRun) {
            stopCamera();
            const name = exception instanceof Error ? exception.name : '';
            if (stage === 'scanner') {
                cameraError.value =
                    'The QR scanner could not load. Refresh this page and try again.';
            } else if (
                [
                    'NotAllowedError',
                    'PermissionDeniedError',
                    'SecurityError',
                ].includes(name)
            ) {
                cameraError.value =
                    'Camera access is blocked by your browser or device. Check site permission and your device camera privacy settings, then try again.';
            } else if (
                ['NotReadableError', 'TrackStartError', 'AbortError'].includes(
                    name,
                )
            ) {
                cameraError.value =
                    'Your camera could not start even though permission may be allowed. Close other apps or tabs using the camera, check the webcam privacy switch, then try again.';
            } else if (
                [
                    'NotFoundError',
                    'DevicesNotFoundError',
                    'OverconstrainedError',
                ].includes(name)
            ) {
                cameraError.value =
                    'No available camera was found. Connect or enable a webcam, or upload a QR photo instead.';
            } else if (stage === 'preview') {
                cameraError.value =
                    'Camera access succeeded, but the video preview could not start. Try again, or upload a QR photo instead.';
            } else {
                cameraError.value =
                    'The camera could not start. Try again, or upload a QR photo instead.';
            }
        }
    }
}
async function findMembers(pageNumber = 1) {
    searchAbort?.abort();
    searchError.value = '';
    members.value = [];
    searched.value = false;
    if (search.value.trim().length === 1) {
        searching.value = false;
        return;
    }
    const abort = new AbortController();
    searchAbort = abort;
    searching.value = true;
    try {
        const response = await fetch(
            CheckInController.search({
                query: {
                    search: search.value.trim(),
                    page: pageNumber,
                    branch_id: branchId.value || undefined,
                },
            }).url,
            {
                signal: abort.signal,
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            },
        );
        if (!response.ok)
            throw new Error(
                response.status === 429
                    ? 'Too many searches. Wait a minute and try again.'
                    : 'Could not search members. Refresh the page and try again.',
            );
        const data = await response.json();
        if (abort.signal.aborted || disposed) return;
        members.value = data.members.data;
        searchPage.value = data.members.current_page;
        lastPage.value = data.members.last_page;
        searched.value = true;
    } catch (exception) {
        if (!abort.signal.aborted && !disposed)
            searchError.value =
                exception instanceof Error
                    ? exception.message
                    : 'Could not search members.';
    } finally {
        if (!abort.signal.aborted) searching.value = false;
    }
}
watch(search, () => {
    searchAbort?.abort();
    clearTimeout(searchTimer);
    members.value = [];
    lastPage.value = 1;
    searchError.value = '';
    searched.value = false;
    searchTimer = setTimeout(() => {
        void findMembers();
    }, 300);
});
watch(branchId, () => {
    imageRun++;
    readingImage.value = false;
    stopCamera();
    notice.value = '';
    error.value = '';
    void findMembers();
});
watch(
    () => props.defaultBranchId,
    (value) => {
        branchId.value = value;
        selectedMembers.value = [];
        void findMembers();
    },
);
function visibilityChanged() {
    if (document.hidden) stopCamera();
}
const removeNavigationListener = router.on('before', () => stopCamera());
onMounted(() => {
    void findMembers();
    document.addEventListener('visibilitychange', visibilityChanged);
});
onBeforeUnmount(() => {
    imageRun++;
    disposed = true;
    stopCamera();
    searchAbort?.abort();
    submissionAbort.abort();
    clearTimeout(searchTimer);
    removeNavigationListener();
    document.removeEventListener('visibilitychange', visibilityChanged);
});
</script>

<template>
    <section
        class="min-w-0 space-y-4 rounded-xl border bg-card p-4"
        aria-labelledby="check-in-heading"
    >
        <h2 id="check-in-heading" class="text-base font-semibold">
            Check in a member
        </h2>
        <p
            v-if="notice"
            role="status"
            class="rounded-lg border border-emerald-500/30 bg-emerald-500/5 p-3 text-sm"
        >
            {{ notice }}
        </p>
        <p v-if="error" role="alert" class="text-sm text-destructive">
            {{ error }}
        </p>
        <div class="flex border-b" role="group" aria-label="Check-in method">
            <button
                type="button"
                class="flex min-h-11 flex-1 items-center justify-center gap-2 border-b-2 px-2 text-sm font-medium focus-visible:ring-2 focus-visible:ring-ring"
                :class="
                    mode === 'search'
                        ? 'border-primary text-primary'
                        : 'border-transparent text-muted-foreground'
                "
                :disabled="busy"
                :aria-pressed="mode === 'search'"
                @click="changeMode('search')"
            >
                <Search class="size-4" aria-hidden="true" />Search member
            </button>
            <button
                type="button"
                class="flex min-h-11 flex-1 items-center justify-center gap-2 border-b-2 px-2 text-sm font-medium focus-visible:ring-2 focus-visible:ring-ring"
                :class="
                    mode === 'qr'
                        ? 'border-primary text-primary'
                        : 'border-transparent text-muted-foreground'
                "
                :disabled="busy"
                :aria-pressed="mode === 'qr'"
                @click="changeMode('qr')"
            >
                <QrCode class="size-4" aria-hidden="true" />Scan QR
            </button>
        </div>
        <div class="space-y-2">
            <Label for="attendance-branch">Check-in branch</Label
            ><NativeSelect
                id="attendance-branch"
                :value="branchId ?? ''"
                :disabled="busy || readingImage"
                @change="
                    branchId = Number(
                        ($event.target as HTMLSelectElement).value,
                    )
                "
                ><option value="" disabled>Choose a branch</option>
                <option
                    v-for="branch in branches"
                    :key="branch.id"
                    :value="branch.id"
                >
                    {{ branch.name }}
                </option></NativeSelect
            >
        </div>
        <form
            v-show="mode === 'search'"
            class="space-y-4"
            @submit.prevent="checkIn()"
        >
            <div class="space-y-2">
                <Label for="attendance-search"
                    >Member name or mobile number</Label
                ><input
                    id="attendance-search"
                    v-model="search"
                    type="search"
                    maxlength="100"
                    :disabled="busy || readingImage"
                    autocomplete="off"
                    placeholder="Search name, mobile or member ID..."
                    class="h-11 w-full min-w-0 rounded-md border border-input bg-transparent px-3 text-base outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring md:text-sm"
                    aria-describedby="attendance-search-help"
                />
                <p
                    id="attendance-search-help"
                    class="text-xs text-muted-foreground"
                >
                    Type at least 2 characters to search.
                </p>
            </div>
            <div
                v-if="selectedMembers.length"
                class="flex flex-wrap gap-2"
                aria-label="Selected members"
            >
                <span
                    v-for="member in selectedMembers"
                    :key="member.id"
                    class="flex max-w-full items-center gap-1 rounded-md bg-primary/5 pl-2 text-sm"
                    ><span class="min-w-0 break-words">{{ member.name }}</span
                    ><button
                        type="button"
                        class="flex size-11 shrink-0 items-center justify-center rounded focus-visible:ring-2 focus-visible:ring-ring"
                        :disabled="busy"
                        :aria-label="'Remove ' + member.name"
                        @click="removeMember(member.id)"
                    >
                        <X class="size-4" aria-hidden="true" /></button
                ></span>
            </div>
            <div class="rounded-lg border p-2">
                <h2 class="px-1 pb-2 text-sm font-medium">Select a member</h2>
                <div
                    class="max-h-80 space-y-2 overflow-y-auto p-1"
                    aria-label="Member search results"
                    :aria-busy="searching"
                >
                    <p
                        v-if="searching"
                        role="status"
                        class="p-2 text-sm text-muted-foreground"
                    >
                        Searching members...
                    </p>
                    <p
                        v-if="searchError"
                        role="alert"
                        class="p-2 text-sm text-destructive"
                    >
                        {{ searchError }}
                    </p>
                    <p
                        v-if="!searching && search.trim().length === 1"
                        class="p-2 text-sm text-muted-foreground"
                    >
                        Enter one more character.
                    </p>
                    <p
                        v-if="searched && !members.length"
                        class="p-2 text-sm text-muted-foreground"
                    >
                        {{
                            search.trim()
                                ? 'No members match your search.'
                                : 'No active members.'
                        }}
                    </p>
                    <button
                        v-for="member in members"
                        :key="member.id"
                        type="button"
                        class="flex min-h-11 w-full items-center gap-3 rounded-lg border p-3 text-left hover:bg-muted/40 focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-60"
                        :class="
                            selectedMembers.some((m) => m.id === member.id)
                                ? 'border-primary/30 bg-primary/5'
                                : ''
                        "
                        :disabled="
                            busy ||
                            readingImage ||
                            !canCheckIn(member) ||
                            selectedMembers.some((m) => m.id === member.id)
                        "
                        @click="selectMember(member)"
                    >
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-full bg-blue-500/10 text-xs font-semibold text-blue-700 dark:text-blue-300"
                            aria-hidden="true"
                            >{{
                                member.name
                                    .split(' ')
                                    .filter(Boolean)
                                    .slice(0, 2)
                                    .map((n) => n[0])
                                    .join('')
                            }}</span
                        >
                        <span class="min-w-0 flex-1"
                            ><span
                                class="block text-sm font-medium break-words"
                                >{{ member.name }}</span
                            ><span
                                class="block text-xs text-muted-foreground"
                                v-if="member.mobileHint"
                                >{{ member.mobileHint }}</span
                            ><span
                                class="mt-1 flex flex-wrap items-center gap-2"
                                ><span
                                    class="rounded px-1.5 py-0.5 text-xs"
                                    :class="
                                        canCheckIn(member)
                                            ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                            : 'bg-muted text-muted-foreground'
                                    "
                                    >{{ member.membershipStatus }}</span
                                ><span
                                    v-if="member.membershipSummary"
                                    class="text-xs text-muted-foreground"
                                    >{{ member.membershipSummary }}</span
                                ></span
                            ></span
                        ><ChevronRight
                            class="size-4 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                    </button>
                </div>
                <nav
                    v-if="lastPage > 1"
                    aria-label="Member search pagination"
                    class="mt-2 flex items-center justify-between gap-2 border-t pt-2"
                >
                    <Button
                        type="button"
                        variant="ghost"
                        :disabled="busy || searching || searchPage <= 1"
                        @click="findMembers(searchPage - 1)"
                        >Previous</Button
                    ><span class="text-xs text-muted-foreground"
                        >{{ searchPage }} / {{ lastPage }}</span
                    ><Button
                        type="button"
                        variant="ghost"
                        :disabled="busy || searching || searchPage >= lastPage"
                        @click="findMembers(searchPage + 1)"
                        >Next</Button
                    >
                </nav>
            </div>
            <Button
                type="submit"
                class="w-full"
                :disabled="
                    busy ||
                    readingImage ||
                    searching ||
                    !branchId ||
                    !selectedMembers.length
                "
                ><CheckCircle class="size-4" aria-hidden="true" />{{
                    busy
                        ? 'Checking in...'
                        : selectedMembers.length > 1
                          ? 'Check in members'
                          : 'Check in member'
                }}</Button
            >
        </form>
        <div
            v-show="mode === 'qr'"
            class="space-y-3"
            aria-label="Attendance QR scanner"
        >
            <div
                class="relative flex aspect-[4/5] max-h-96 w-full items-center justify-center overflow-hidden rounded-lg bg-zinc-950 text-white"
            >
                <video
                    v-show="cameraActive"
                    ref="video"
                    aria-label="Attendance QR camera preview"
                    autoplay
                    muted
                    playsinline
                    class="absolute inset-0 h-full w-full object-cover"
                />
                <div
                    class="pointer-events-none absolute inset-8 rounded-2xl border-2 border-white/50"
                    aria-hidden="true"
                />
                <div
                    v-if="!cameraActive"
                    class="relative flex max-w-56 flex-col items-center gap-4 px-6 text-center"
                >
                    <span class="rounded-full bg-white/10 p-4"
                        ><QrCode class="size-8" aria-hidden="true"
                    /></span>
                    <p class="text-sm">
                        Position the member's attendance QR within the frame.
                    </p>
                </div>
            </div>
            <Button
                v-if="!cameraActive"
                type="button"
                class="w-full"
                :disabled="busy || readingImage || !branchId"
                @click="startCamera"
                ><Camera class="size-4" aria-hidden="true" />Start
                camera</Button
            ><Button
                v-else
                type="button"
                class="w-full"
                variant="outline"
                @click="stopCamera"
                >Stop camera</Button
            >
            <input
                ref="photoInput"
                class="sr-only"
                tabindex="-1"
                type="file"
                accept="image/jpeg,image/png,image/webp"
                aria-label="Choose an attendance QR image"
                @change="scanPhoto"
            />
            <Button
                type="button"
                variant="outline"
                class="w-full"
                :disabled="busy || readingImage || !branchId"
                @click="photoInput?.click()"
                ><Upload class="size-4" aria-hidden="true" />{{
                    readingImage ? 'Reading QR...' : 'Upload QR photo'
                }}</Button
            >
            <p
                v-if="cameraActive"
                role="status"
                class="text-xs text-muted-foreground"
            >
                A valid QR checks the member in automatically.
            </p>
            <p v-if="cameraError" role="alert" class="text-sm text-destructive">
                {{ cameraError }}
            </p>
            <div class="flex items-start gap-3 rounded-lg bg-muted/30 p-3">
                <QrCode class="size-5 shrink-0" aria-hidden="true" />
                <div>
                    <p class="text-sm font-medium">Scan member's QR code</p>
                    <p class="text-xs text-muted-foreground">
                        Attendance QR only. QR photos are read on your device.
                    </p>
                </div>
            </div>
        </div>
    </section>
</template>
