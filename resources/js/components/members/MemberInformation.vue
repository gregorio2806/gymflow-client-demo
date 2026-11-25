<script setup lang="ts">
import { formatDisplayDate } from '@/lib/date-format';
import { Link, usePage } from '@inertiajs/vue3';
import { Pencil, QrCode, UserRound } from '@lucide/vue';
import MemberController from '@/actions/App/Http/Controllers/Dashboard/MemberController';
import MemberPortalController from '@/actions/App/Http/Controllers/Dashboard/MemberPortalController';
import MemberQrController from '@/actions/App/Http/Controllers/Dashboard/MemberQrController';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import type { MemberProfile } from '@/types/member-profile';
const props = defineProps<{ member: MemberProfile; canManage: boolean }>();
const page = usePage();
</script>
<template>
    <div class="space-y-4">
        <section class="rounded-xl border bg-card p-4 sm:p-5">
            <header
                class="mb-4 flex items-center justify-between gap-3 border-b pb-3"
            >
                <h2 class="flex items-center gap-2 text-base font-semibold">
                    <UserRound class="size-5" />Member information
                </h2>
                <div class="flex flex-wrap justify-end gap-2">
                    <Button
                        v-if="
                            canManage &&
                            page.props.features.includes('qr-attendance')
                        "
                        variant="outline"
                        as-child
                    >
                        <Link
                            :href="MemberQrController.show(member.id)"
                            :aria-label="`Generate attendance QR for ${member.name}`"
                            ><QrCode class="size-4" />Generate QR</Link
                        >
                    </Button>
                    <Button v-if="canManage" variant="outline" as-child
                        ><Link :href="MemberController.edit(member.id)"
                            ><Pencil class="size-4" />Edit</Link
                        ></Button
                    >
                </div>
            </header>
            <dl
                class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-5 gap-y-3 text-sm"
            >
                <dt class="text-muted-foreground">Member ID</dt>
                <dd>{{ member.id }}</dd>
                <dt class="text-muted-foreground">Full name</dt>
                <dd class="break-words">{{ member.name }}</dd>
                <dt class="text-muted-foreground">Mobile number</dt>
                <dd class="break-words">
                    {{ member.mobile || 'Not provided' }}
                </dd>
                <dt class="text-muted-foreground">Email</dt>
                <dd class="break-all">{{ member.email || 'Not provided' }}</dd>
                <dt class="text-muted-foreground">Home branch</dt>
                <dd class="break-words">{{ member.branchName }}</dd>
                <dt class="text-muted-foreground">Membership status</dt>
                <dd>
                    <Badge variant="outline">{{
                        member.membershipStatus
                    }}</Badge>
                </dd>
                <dt class="text-muted-foreground">Profile status</dt>
                <dd>{{ member.is_active ? 'Active' : 'Inactive' }}</dd>
                <dt class="text-muted-foreground">Consent</dt>
                <dd>{{ member.hasConsent ? 'Recorded' : 'Not recorded' }}</dd>
                <dt class="text-muted-foreground">Joined date</dt>
                <dd>{{ formatDisplayDate(member.joinedOn) }}</dd>
            </dl>
            <Link
                v-if="page.props.features.includes('member-auth')"
                :href="MemberPortalController.show(member.id)"
                class="mt-4 inline-flex min-h-11 items-center text-sm underline underline-offset-4"
                >Manage member portal</Link
            >
        </section>
    </div>
</template>
