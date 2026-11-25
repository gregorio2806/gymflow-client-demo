<script setup lang="ts">
import RecordList from '@/components/RecordList.vue';
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { Pencil, Plus, Building2, MapPin, Phone } from '@lucide/vue';
import BranchController from '@/actions/App/Http/Controllers/Dashboard/BranchController';
import PlanSelectionController from '@/actions/App/Http/Controllers/Dashboard/PlanSelectionController';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
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
import { index } from '@/routes/branches';

type Branch = {
    id: number;
    name: string;
    address: string | null;
    phone: string | null;
    is_main: boolean;
    is_active: boolean;
    canEdit: boolean;
};
const page = usePage();
const props = defineProps<{
    branches: {
        data: Branch[];
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    limit: number;
    plan: string;
    canAdd: boolean;
    canSelect: boolean;
    activeCount: number;
    activeBranchIds: number[];
    activeBranches: { id: number; name: string }[];
    needsSelection: boolean;
}>();
const selectedBranches = ref([...props.activeBranchIds]);
const pendingChange = ref<{
    branch: Branch;
    checked: boolean;
    replacing: number[];
    blocked: boolean;
} | null>(null);
const branchName = (id: number) =>
    props.branches.data.find((branch) => branch.id === id)?.name ??
    props.activeBranches.find((branch) => branch.id === id)?.name ??
    'another selected branch';
const confirmationMessage = computed(() => {
    const change = pendingChange.value;
    if (!change) return '';
    if (change.blocked)
        return `Your plan allows ${props.limit} active branches. Uncheck another branch before selecting ${change.branch.name}.`;
    if (change.replacing.length)
        return `Your plan allows only one active branch. ${change.replacing.map(branchName).join(', ')} will become inactive (read-only), and ${change.branch.name} will become active when you save.`;
    return change.checked
        ? `Are you sure you want to activate ${change.branch.name}?`
        : `Are you sure you want to make ${change.branch.name} inactive (read-only)? Its existing records will be kept.`;
});
watch(
    () => props.activeBranchIds,
    (ids) => {
        selectedBranches.value = [...ids];
        pendingChange.value = null;
    },
);
function toggleBranch(branch: Branch, event: Event) {
    const input = event.target as HTMLInputElement;
    const checked = input.checked;
    input.checked = selectedBranches.value.includes(branch.id);
    pendingChange.value = {
        branch,
        checked,
        replacing:
            checked && props.limit === 1
                ? selectedBranches.value.filter((id) => id !== branch.id)
                : [],
        blocked:
            checked &&
            props.limit > 1 &&
            selectedBranches.value.length >= props.limit,
    };
}
function confirmBranchChange() {
    const change = pendingChange.value;
    if (!change || change.blocked) return;
    selectedBranches.value = change.checked
        ? props.limit === 1
            ? [change.branch.id]
            : [...new Set([...selectedBranches.value, change.branch.id])]
        : selectedBranches.value.filter((id) => id !== change.branch.id);
    pendingChange.value = null;
}
defineOptions({
    layout: { breadcrumbs: [{ title: 'Branches', href: index() }] },
});
</script>

<template>
    <Head title="Branches" />
    <div class="space-y-5 p-4 sm:p-6">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <span
                    class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/5 text-primary"
                    ><Building2 class="size-6" aria-hidden="true"
                /></span>
                <div>
                    <h1 class="text-2xl font-semibold">
                        {{
                            page.props.currentBranch
                                ? 'Your branch'
                                : 'Branches'
                        }}
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{
                            page.props.currentBranch
                                ? 'View and manage your assigned location.'
                                : 'Manage your gym locations and contact details.'
                        }}
                    </p>
                </div>
            </div>
            <Dialog v-if="canAdd">
                <DialogTrigger as-child
                    ><Button
                        ><Plus class="size-4" />Add branch</Button
                    ></DialogTrigger
                >
                <DialogContent>
                    <DialogHeader
                        ><DialogTitle>Add branch</DialogTitle
                        ><DialogDescription
                            >Enter the location's details.</DialogDescription
                        ></DialogHeader
                    >
                    <Form
                        v-bind="BranchController.store.form()"
                        v-slot="{ errors, processing, wasSuccessful }"
                        class="space-y-4"
                        reset-on-success
                    >
                        <p v-if="wasSuccessful" role="status" class="text-sm">
                            Branch added.
                        </p>
                        <div class="grid gap-2">
                            <Label for="branch-name">Name</Label
                            ><Input
                                id="branch-name"
                                name="name"
                                required
                                maxlength="255"
                            /><InputError :message="errors.name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="branch-address"
                                >Address (optional)</Label
                            ><Input
                                id="branch-address"
                                name="address"
                                maxlength="1000"
                                autocomplete="street-address"
                            /><InputError :message="errors.address" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="branch-phone">Phone (optional)</Label
                            ><Input
                                id="branch-phone"
                                name="phone"
                                type="tel"
                                maxlength="30"
                                autocomplete="tel"
                            /><InputError :message="errors.phone" />
                        </div>
                        <DialogFooter
                            ><DialogClose as-child
                                ><Button variant="secondary"
                                    >Close</Button
                                ></DialogClose
                            ><Button type="submit" :disabled="processing">{{
                                processing ? 'Adding...' : 'Add branch'
                            }}</Button></DialogFooter
                        >
                    </Form>
                </DialogContent>
            </Dialog>
        </header>
        <section class="space-y-4">
            <p
                v-if="!canAdd && !page.props.currentBranch"
                class="mb-4 text-sm text-muted-foreground"
            >
                Additional branches are read-only until your plan has room for
                them.
            </p>
            <Form
                v-if="canSelect"
                v-bind="PlanSelectionController.selectBranches.form()"
                v-slot="{ processing, errors }"
                class="flex flex-wrap items-center gap-3 rounded-xl border bg-card p-3"
            >
                <p v-if="needsSelection" class="w-full text-sm">
                    The oldest branches stayed active within your new allowance.
                    Select the branches you want to keep active.
                </p>
                <input
                    v-for="id in selectedBranches"
                    :key="id"
                    type="hidden"
                    name="branch_ids[]"
                    :value="id"
                />
                <p class="text-sm text-muted-foreground">
                    <span class="font-medium text-foreground"
                        >{{ selectedBranches.length }} of {{ limit }}</span
                    >
                    active branches selected
                    <span class="ml-2 text-xs">{{ plan }}</span>
                </p>
                <InputError :message="errors.branch_ids" />
                <Button
                    type="submit"
                    variant="outline"
                    class="sm:ml-auto"
                    :disabled="
                        processing ||
                        selectedBranches.length < 1 ||
                        selectedBranches.length > limit
                    "
                    >Save active branches</Button
                >
            </Form>
            <RecordList
                :rows="branches.data"
                :columns="[
                    { key: 'name', label: 'Branch' },
                    { key: 'address', label: 'Address' },
                    { key: 'phone', label: 'Phone' },
                    { key: 'status', label: 'Status' },
                    ...(canSelect
                        ? [
                              {
                                  key: 'selected',
                                  label: 'Keep active',
                                  width: '8rem',
                              },
                          ]
                        : []),
                    { key: 'actions', label: 'Actions' },
                ]"
                caption="Gym branches"
                row-key="id"
                empty="No branches to show."
                ><template #cell-name="{ row: branch }"
                    ><div class="flex items-center gap-3">
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400"
                            ><Building2 class="size-4" aria-hidden="true"
                        /></span>
                        <div>
                            <p class="font-medium break-words">
                                {{ branch.name }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{
                                    branch.is_main
                                        ? 'Main branch'
                                        : 'Gym location'
                                }}
                            </p>
                        </div>
                    </div></template
                ><template #cell-address="{ row: branch }"
                    ><span class="flex items-start gap-2 text-muted-foreground"
                        ><MapPin
                            class="mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        /><span class="break-words">{{
                            branch.address || 'Not provided'
                        }}</span></span
                    ></template
                ><template #cell-phone="{ row: branch }"
                    ><span class="flex items-center gap-2"
                        ><Phone
                            class="size-4 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />{{ branch.phone || 'Not provided' }}</span
                    ></template
                ><template #cell-status="{ row: branch }"
                    ><Badge
                        :class="
                            branch.is_active
                                ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400'
                                : 'bg-muted text-muted-foreground'
                        "
                        >{{ branch.is_active ? 'Active' : 'Read-only' }}</Badge
                    ></template
                ><template #cell-selected="{ row: branch }"
                    ><label
                        v-if="canSelect"
                        :for="`active-branch-${branch.id}`"
                        class="inline-flex min-h-11 min-w-11 cursor-pointer items-center justify-center"
                    >
                        <input
                            :id="`active-branch-${branch.id}`"
                            type="checkbox"
                            :checked="selectedBranches.includes(branch.id)"
                            class="size-5 shrink-0 accent-primary"
                            @change="toggleBranch(branch, $event)"
                        />
                        <span class="sr-only"
                            >Keep {{ branch.name }} active</span
                        >
                    </label></template
                ><template #cell-actions="{ row: branch }"
                    ><Dialog v-if="branch.canEdit">
                        <DialogTrigger as-child
                            ><Button
                                variant="outline"
                                size="icon"
                                :aria-label="`Edit ${branch.name}`"
                                :title="`Edit ${branch.name}`"
                                ><Pencil class="size-5" /></Button
                        ></DialogTrigger>
                        <DialogContent>
                            <DialogHeader
                                ><DialogTitle>Edit branch</DialogTitle
                                ><DialogDescription>{{
                                    branch.name
                                }}</DialogDescription></DialogHeader
                            >
                            <Form
                                v-bind="BranchController.update.form(branch.id)"
                                v-slot="{ errors, processing, wasSuccessful }"
                                class="space-y-4"
                            >
                                <p
                                    v-if="wasSuccessful"
                                    role="status"
                                    class="text-sm"
                                >
                                    Branch updated.
                                </p>
                                <div class="grid gap-2">
                                    <Label :for="`name-${branch.id}`"
                                        >Name</Label
                                    ><Input
                                        :id="`name-${branch.id}`"
                                        name="name"
                                        :default-value="branch.name"
                                        required
                                        maxlength="255"
                                    /><InputError :message="errors.name" />
                                </div>
                                <div class="grid gap-2">
                                    <Label :for="`address-${branch.id}`"
                                        >Address (optional)</Label
                                    ><Input
                                        :id="`address-${branch.id}`"
                                        name="address"
                                        :default-value="branch.address ?? ''"
                                        maxlength="1000"
                                        autocomplete="street-address"
                                    /><InputError :message="errors.address" />
                                </div>
                                <div class="grid gap-2">
                                    <Label :for="`phone-${branch.id}`"
                                        >Phone (optional)</Label
                                    ><Input
                                        :id="`phone-${branch.id}`"
                                        name="phone"
                                        :default-value="branch.phone ?? ''"
                                        type="tel"
                                        maxlength="30"
                                        autocomplete="tel"
                                    /><InputError :message="errors.phone" />
                                </div>
                                <DialogFooter
                                    ><DialogClose as-child
                                        ><Button variant="secondary"
                                            >Close</Button
                                        ></DialogClose
                                    ><Button
                                        type="submit"
                                        :disabled="processing"
                                        >{{
                                            processing
                                                ? 'Saving...'
                                                : 'Save changes'
                                        }}</Button
                                    ></DialogFooter
                                >
                            </Form>
                        </DialogContent>
                    </Dialog></template
                ></RecordList
            >
            <footer class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-muted-foreground">
                    {{ branches.total }}
                    {{ branches.total === 1 ? 'branch' : 'branches' }}
                </p>
                <nav
                    v-if="branches.prev_page_url || branches.next_page_url"
                    aria-label="Pagination"
                    class="flex gap-2"
                >
                    <Button
                        v-if="branches.prev_page_url"
                        variant="outline"
                        as-child
                        ><Link :href="branches.prev_page_url"
                            >Previous</Link
                        ></Button
                    ><span v-else />
                    <Button
                        v-if="branches.next_page_url"
                        variant="outline"
                        as-child
                        ><Link :href="branches.next_page_url"
                            >Next</Link
                        ></Button
                    >
                </nav>
            </footer>
        </section>
        <Dialog
            :open="!!pendingChange"
            @update:open="
                (open) => {
                    if (!open) pendingChange = null;
                }
            "
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{
                        pendingChange?.blocked
                            ? 'Branch limit reached'
                            : pendingChange?.replacing.length
                              ? 'Switch active branch?'
                              : pendingChange?.checked
                                ? 'Activate branch?'
                                : 'Make branch inactive?'
                    }}</DialogTitle>
                    <DialogDescription>{{
                        confirmationMessage
                    }}</DialogDescription>
                </DialogHeader>
                <p
                    v-if="!pendingChange?.blocked"
                    class="text-sm text-muted-foreground"
                >
                    Confirm updates your selection. Click Save active branches
                    to apply it. At least one branch must stay active.
                </p>
                <DialogFooter>
                    <DialogClose as-child
                        ><Button variant="outline">{{
                            pendingChange?.blocked ? 'Close' : 'Cancel'
                        }}</Button></DialogClose
                    >
                    <Button
                        v-if="!pendingChange?.blocked"
                        @click="confirmBranchChange"
                        >Confirm selection</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
