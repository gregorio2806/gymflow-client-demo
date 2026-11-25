<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Eye, Pencil, QrCode } from '@lucide/vue';
import { usePage } from '@inertiajs/vue3';
import MemberController from '@/actions/App/Http/Controllers/Dashboard/MemberController';
import MemberQrController from '@/actions/App/Http/Controllers/Dashboard/MemberQrController';
import { Button } from '@/components/ui/button';
import type { MemberRow } from '@/types/members';
defineProps<{ member: MemberRow; paymentSelection?: boolean }>();
const page = usePage<{ features: string[] }>();
</script>
<template>
    <div class="flex items-center justify-end gap-2">
        <Button variant="outline" size="icon" as-child
            ><Link
                :href="MemberController.show(member.id)"
                :aria-label="`View ${member.name}`"
                :title="`View ${member.name}`"
                ><Eye class="size-5" aria-hidden="true" /></Link
        ></Button>
        <Button
            v-if="
                page.props.features.includes('qr-attendance') && member.canEdit
            "
            variant="outline"
            size="icon"
            as-child
        >
            <Link
                :href="MemberQrController.show(member.id)"
                :aria-label="`Attendance QR for ${member.name}`"
                :title="`Attendance QR for ${member.name}`"
                ><QrCode class="size-5" aria-hidden="true"
            /></Link>
        </Button>
        <Button v-if="member.canEdit" variant="outline" size="icon" as-child
            ><Link
                :href="MemberController.edit(member.id)"
                :aria-label="`Edit ${member.name}`"
                :title="`Edit ${member.name}`"
                ><Pencil class="size-5" aria-hidden="true" /></Link
        ></Button>
    </div>
</template>
