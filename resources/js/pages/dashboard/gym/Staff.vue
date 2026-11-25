<script setup lang="ts">
import RecordList from '@/components/RecordList.vue';
import TeamBranchSelect from '@/components/TeamBranchSelect.vue';
import { ref } from 'vue';
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { MailPlus, Pencil, Users } from '@lucide/vue';
import PlanSelectionController from '@/actions/App/Http/Controllers/Dashboard/PlanSelectionController';
import StaffController from '@/actions/App/Http/Controllers/Dashboard/StaffController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { index } from '@/routes/staff';
import type { GymRole } from '@/types';

const props = defineProps<{
    canManage: boolean;
    needsSelection: boolean;
    teamPagination: { total: number; prev: string | null; next: string | null };
    invitePagination: {
        total: number;
        prev: string | null;
        next: string | null;
    } | null;
    staffCapacity: { used: number; limit: number; perBranch: boolean };
    branches: { id: number; name: string }[];
    members: {
        id: number;
        name: string;
        email: string;
        role: GymRole;
        isYou: boolean;
        isActive: boolean;
        branchId: number | null;
        branchIds: number[];
        canEditName: boolean;
    }[];
    invitations: {
        id: number;
        email: string;
        role: GymRole;
        expiresAt: string;
        branchId: number;
        branchIds: number[];
    }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Staff', href: index() }],
    },
});

const page = usePage();
const inviteRole = ref('staff');
const editRoles = ref<Record<number, string>>({});
function branchNames(ids: number[]) {
    return ids
        .map((id) => props.branches.find((branch) => branch.id === id)?.name)
        .filter(Boolean)
        .join(', ');
}

const roleLabel: Record<GymRole, string> = {
    owner: 'Owner',
    staff: 'Staff',
    coach: 'Coach',
    'branch-manager': 'Branch manager',
};

const expiryDate = (iso: string) =>
    new Date(iso).toLocaleDateString('en-PH', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
</script>

<template>
    <Head title="Staff" />

    <div class="space-y-10 px-4 py-6 sm:px-6">
        <h1 class="sr-only">Staff</h1>

        <!-- Invite -->
        <section v-if="canManage && needsSelection" class="max-w-3xl space-y-3">
            <p class="text-sm">
                The longest-serving staff stayed active within your new
                allowance. Inactive accounts keep their records and can be
                reactivated when a place is available.
            </p>
            <Form
                v-bind="PlanSelectionController.confirm.form()"
                v-slot="{ processing }"
            >
                <input type="hidden" name="selection" value="staff" />
                <Button type="submit" variant="outline" :disabled="processing"
                    >Confirm active staff</Button
                >
            </Form>
        </section>
        <section v-if="canManage" class="max-w-xl">
            <Heading
                title="Invite staff"
                description="We'll email them a link to join your gym. Staff can manage members and check-ins; coaches manage workouts."
            />

            <p class="mb-4 text-sm text-muted-foreground">
                <template v-if="staffCapacity.perBranch"
                    >Your plan includes {{ staffCapacity.limit }} staff places
                    per branch, including pending invitations. Each person uses
                    a place in every assigned branch.</template
                >
                <template v-else
                    >{{ staffCapacity.used }} of {{ staffCapacity.limit }} staff
                    places used (including pending invitations).</template
                >
                Owners and branch managers do not count.
                <span
                    v-if="
                        !staffCapacity.perBranch &&
                        staffCapacity.used >= staffCapacity.limit
                    "
                    >Cancel a pending invitation or upgrade to add more.</span
                >
            </p>
            <p
                v-if="page.props.demo"
                class="rounded-xl border border-dashed p-4 text-sm text-muted-foreground"
            >
                Inviting staff is turned off in the demo (it sends real emails).
                Create your own gym to invite your team.
            </p>
            <Form
                v-else
                v-bind="StaffController.invite.form()"
                :reset-on-success="['email']"
                v-slot="{ errors, processing }"
                class="grid gap-4 sm:grid-cols-[1fr_10rem_auto] sm:items-end"
            >
                <div class="grid gap-2">
                    <Label for="email">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        required
                        maxlength="255"
                        autocomplete="off"
                        placeholder="e.g. juan@example.com"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="role">Role</Label>
                    <NativeSelect
                        id="role"
                        name="role"
                        required
                        v-model="inviteRole"
                    >
                        <option value="staff">Staff</option>
                        <option value="coach">Coach</option>
                        <option
                            v-if="
                                page.props.currentGym?.plan === 'multi_branch'
                            "
                            value="branch-manager"
                        >
                            Branch manager
                        </option>
                    </NativeSelect>
                    <InputError :message="errors.role" />
                </div>

                <Button
                    type="submit"
                    :disabled="processing"
                    data-test="invite-staff-button"
                >
                    <Spinner v-if="processing" />
                    <MailPlus v-else class="size-4" />
                    {{ processing ? 'Sending…' : 'Send invite' }}
                </Button>
                <div class="grid gap-2 sm:col-span-full">
                    <TeamBranchSelect
                        :branches="branches"
                        :initial-ids="
                            branches.slice(0, 1).map((branch) => branch.id)
                        "
                        :role="inviteRole"
                    />
                    <InputError
                        :message="errors.branch_ids || errors['branch_ids.0']"
                    />
                    <InputError :message="errors.branch_id" />
                </div>
            </Form>
        </section>

        <!-- Pending invitations -->
        <section v-if="invitations.length">
            <Heading
                variant="small"
                title="Pending invitations"
                description="Links expire after 7 days."
            />
            <RecordList
                :rows="invitations"
                :columns="
                    [
                        { key: 'email', label: 'Email' },
                        { key: 'role', label: 'Role' },
                        { key: 'branch', label: 'Branches' },
                        { key: 'expiresAt', label: 'Expires' },
                        { key: 'actions', label: 'Actions' },
                    ].filter((column) => canManage || column.key !== 'actions')
                "
                caption="Pending team invitations"
                row-key="id"
                class="mt-4"
                ><template #cell-email="{ row: invitation }"
                    ><span class="font-medium break-all">{{
                        invitation.email
                    }}</span></template
                ><template #cell-role="{ row: invitation }">{{
                    roleLabel[invitation.role]
                }}</template
                ><template #cell-branch="{ row: invitation }">{{
                    branchNames(invitation.branchIds)
                }}</template
                ><template #cell-expiresAt="{ row: invitation }">{{
                    expiryDate(invitation.expiresAt)
                }}</template
                ><template #cell-actions="{ row: invitation }"
                    ><Dialog>
                        <DialogTrigger as-child>
                            <Button variant="outline"> Cancel invite </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle
                                    >Cancel this invitation?</DialogTitle
                                >
                                <DialogDescription>
                                    The link sent to {{ invitation.email }} will
                                    stop working. You can invite them again
                                    later.
                                </DialogDescription>
                            </DialogHeader>
                            <Form
                                v-bind="
                                    StaffController.revoke.form(invitation.id)
                                "
                                v-slot="{ processing }"
                            >
                                <DialogFooter class="gap-2">
                                    <DialogClose as-child>
                                        <Button variant="secondary">
                                            Keep invite
                                        </Button>
                                    </DialogClose>
                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        :disabled="processing"
                                    >
                                        Cancel invitation
                                    </Button>
                                </DialogFooter>
                            </Form>
                        </DialogContent>
                    </Dialog></template
                ></RecordList
            >
        </section>

        <nav
            v-if="invitePagination?.prev || invitePagination?.next"
            aria-label="Invitation pagination"
            class="flex justify-between"
        >
            <Button v-if="invitePagination?.prev" variant="outline" as-child
                ><Link :href="invitePagination.prev">Previous</Link></Button
            ><span v-else />
            <Button v-if="invitePagination?.next" variant="outline" as-child
                ><Link :href="invitePagination.next">Next</Link></Button
            >
        </nav>
        <!-- Team -->
        <section class="@container">
            <Heading
                variant="small"
                :title="canManage ? 'Your team' : 'Branch team'"
                :description="`${teamPagination.total} ${teamPagination.total === 1 ? 'person' : 'people'}`"
            />
            <RecordList
                :rows="members"
                :columns="
                    [
                        { key: 'name', label: 'Team member', width: '27%' },
                        { key: 'role', label: 'Role', width: '15%' },
                        { key: 'branch', label: 'Branches', width: '20%' },
                        { key: 'status', label: 'Status', width: '13%' },
                        { key: 'actions', label: 'Actions', width: '25%' },
                    ].filter((column) => canManage || column.key !== 'actions')
                "
                caption="Gym team"
                row-key="id"
                class="mt-4"
                ><template #cell-name="{ row: member }"
                    ><div class="flex items-center gap-3">
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                            ><Users class="size-5"
                        /></span>
                        <div class="min-w-0">
                            <p class="font-medium">
                                {{ member.name }}
                                <span
                                    v-if="member.isYou"
                                    class="text-xs text-muted-foreground"
                                    >(you)</span
                                >
                            </p>
                            <p
                                class="mt-1 text-xs break-all text-muted-foreground"
                            >
                                {{ member.email }}
                            </p>
                        </div>
                    </div></template
                ><template #cell-role="{ row: member }"
                    ><Badge variant="secondary">{{
                        roleLabel[member.role]
                    }}</Badge></template
                ><template #cell-branch="{ row: member }">{{
                    branchNames(member.branchIds) || 'All branches'
                }}</template
                ><template #cell-status="{ row: member }"
                    ><Badge
                        :class="
                            member.isActive
                                ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400'
                                : 'bg-muted text-muted-foreground'
                        "
                        >{{ member.isActive ? 'Active' : 'Inactive' }}</Badge
                    ></template
                ><template #cell-actions="{ row: member }"
                    ><div
                        class="flex min-w-0 flex-wrap items-center justify-end gap-2"
                    >
                        <Form
                            v-if="canManage && member.role !== 'owner'"
                            v-bind="
                                PlanSelectionController.staff.form(member.id)
                            "
                            v-slot="{ processing, errors }"
                            class="contents"
                        >
                            <input
                                type="hidden"
                                name="is_active"
                                :value="member.isActive ? '0' : '1'"
                            />
                            <button
                                type="submit"
                                role="switch"
                                :aria-checked="member.isActive"
                                :aria-busy="processing"
                                :aria-describedby="
                                    errors.is_active || errors.role
                                        ? `activation-error-${member.id}`
                                        : undefined
                                "
                                class="flex h-11 w-14 shrink-0 cursor-pointer items-center justify-center rounded-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:opacity-60"
                                :disabled="processing"
                                :title="
                                    member.isActive
                                        ? 'Active: deactivate in this gym'
                                        : 'Inactive: activate in this gym'
                                "
                                :aria-label="`${member.name}: active in this gym`"
                            >
                                <span
                                    aria-hidden="true"
                                    class="relative h-7 w-13 rounded-full transition-colors duration-200 motion-reduce:transition-none"
                                    :class="
                                        member.isActive
                                            ? 'bg-green-700'
                                            : 'bg-red-700'
                                    "
                                >
                                    <span
                                        class="absolute top-1 left-1 size-5 rounded-full bg-white shadow-sm transition-transform duration-200 motion-reduce:transition-none"
                                        :class="
                                            member.isActive
                                                ? 'translate-x-6'
                                                : 'translate-x-0'
                                        "
                                    />
                                </span>
                            </button>
                            <InputError
                                :id="`activation-error-${member.id}`"
                                role="alert"
                                class="order-last col-span-full min-w-0 break-words"
                                :message="errors.is_active || errors.role"
                            />
                        </Form>
                        <Dialog v-if="canManage">
                            <DialogTrigger as-child>
                                <Button
                                    variant="outline"
                                    size="icon"
                                    :aria-label="`Edit ${member.name}`"
                                >
                                    <Pencil class="size-5" />
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>Edit team member</DialogTitle>
                                    <DialogDescription>
                                        {{ member.email }}
                                    </DialogDescription>
                                </DialogHeader>
                                <Form
                                    v-bind="
                                        StaffController.update.form(member.id)
                                    "
                                    v-slot="{ errors, processing }"
                                    class="space-y-5"
                                >
                                    <div class="grid gap-2">
                                        <Label :for="`name-${member.id}`"
                                            >Name</Label
                                        >
                                        <Input
                                            :id="`name-${member.id}`"
                                            name="name"
                                            :default-value="member.name"
                                            :readonly="!member.canEditName"
                                            required
                                            maxlength="255"
                                            autocomplete="off"
                                        />
                                        <p
                                            v-if="!member.canEditName"
                                            class="text-sm text-muted-foreground"
                                        >
                                            They also work at another gym, so
                                            only they can change their name.
                                        </p>
                                        <InputError :message="errors.name" />
                                    </div>

                                    <div
                                        v-if="member.role !== 'owner'"
                                        class="grid gap-2"
                                    >
                                        <Label :for="`role-${member.id}`"
                                            >Role</Label
                                        >
                                        <NativeSelect
                                            :id="`role-${member.id}`"
                                            @change="
                                                editRoles[member.id] = (
                                                    $event.target as HTMLSelectElement
                                                ).value
                                            "
                                            name="role"
                                            required
                                        >
                                            <option
                                                value="staff"
                                                :selected="
                                                    member.role === 'staff'
                                                "
                                            >
                                                Staff
                                            </option>
                                            <option
                                                value="coach"
                                                :selected="
                                                    member.role === 'coach'
                                                "
                                            >
                                                Coach
                                            </option>
                                            <option
                                                v-if="
                                                    page.props.currentGym
                                                        ?.plan ===
                                                    'multi_branch'
                                                "
                                                value="branch-manager"
                                                :selected="
                                                    member.role ===
                                                    'branch-manager'
                                                "
                                            >
                                                Branch manager
                                            </option>
                                        </NativeSelect>
                                        <InputError :message="errors.role" />
                                    </div>
                                    <p
                                        v-else
                                        class="text-sm text-muted-foreground"
                                    >
                                        The owner's role can't be changed.
                                    </p>

                                    <div
                                        v-if="member.role !== 'owner'"
                                        class="grid gap-2"
                                    >
                                        <TeamBranchSelect
                                            :branches="branches"
                                            :initial-ids="member.branchIds"
                                            :role="
                                                editRoles[member.id] ||
                                                member.role
                                            "
                                        />
                                        <InputError
                                            :message="
                                                errors.branch_ids ||
                                                errors['branch_ids.0']
                                            "
                                        />
                                        <InputError
                                            :message="errors.branch_id"
                                        />
                                    </div>
                                    <DialogFooter class="gap-2">
                                        <DialogClose as-child>
                                            <Button variant="secondary"
                                                >Cancel</Button
                                            >
                                        </DialogClose>
                                        <Button
                                            type="submit"
                                            :disabled="processing"
                                        >
                                            <Spinner v-if="processing" />
                                            {{
                                                processing
                                                    ? 'Saving…'
                                                    : 'Save changes'
                                            }}
                                        </Button>
                                    </DialogFooter>
                                </Form>
                            </DialogContent>
                        </Dialog>
                    </div></template
                ></RecordList
            >
        </section>
        <nav
            v-if="teamPagination.prev || teamPagination.next"
            aria-label="Team pagination"
            class="flex justify-between"
        >
            <Button v-if="teamPagination.prev" variant="outline" as-child
                ><Link :href="teamPagination.prev">Previous</Link></Button
            ><span v-else />
            <Button v-if="teamPagination.next" variant="outline" as-child
                ><Link :href="teamPagination.next">Next</Link></Button
            >
        </nav>
    </div>
</template>
