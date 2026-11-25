<script setup lang="ts">
import RecordList from '@/components/RecordList.vue';
import { formatDisplayDate } from '@/lib/date-format';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import WorkoutRoutineController from '@/actions/App/Http/Controllers/Dashboard/WorkoutRoutineController';
import InputError from '@/components/InputError.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
type Exercise = { name: string; sets: number; reps: string; notes: string };
type Day = { name: string; exercises: Exercise[] };
type Pages<T> = {
    data: T[];
    prev_page_url: string | null;
    next_page_url: string | null;
};
const props = defineProps<{
    routine: {
        id: number;
        name: string;
        notes: string | null;
        days: Day[];
        branch_id: number;
        branch: string;
        revision: number;
    } | null;
    branches: Pages<{ id: number; name: string; is_active: boolean }> | null;
    members: Pages<{ id: number; name: string }> | null;
    assignments: Pages<{
        id: number;
        member: string;
        revision: number;
        createdAt: string;
        createdBy: string;
        assignedAt: string;
    }> | null;
    search: string;
    requestKey: string | null;
    writable: boolean;
}>();
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Workouts', href: WorkoutRoutineController.index() },
        ],
    },
});
const exercise = (): Exercise => ({ name: '', sets: 3, reps: '10', notes: '' });
const form = useForm({
    name: props.routine?.name ?? '',
    notes: props.routine?.notes ?? '',
    branch_id:
        props.routine?.branch_id ??
        props.branches?.data.find((b) => b.is_active)?.id ??
        '',
    request_key: props.requestKey,
    revision: props.routine?.revision ?? null,
    days: props.routine
        ? (JSON.parse(JSON.stringify(props.routine.days)) as Day[])
        : ([{ name: 'Day 1', exercises: [exercise()] }] as Day[]),
});
const assignment = useForm({
    member_id: '',
    revision: props.routine?.revision ?? 1,
});
const selectedDay = ref(0);
const day = computed(() => form.days[selectedDay.value]);
const search = ref(props.search);
const error = (key: string) => (form.errors as Record<string, string>)[key];
const canWrite = computed(
    () =>
        props.writable &&
        (props.routine ||
            props.branches?.data.some(
                (b) => b.id === Number(form.branch_id) && b.is_active,
            )),
);
function save() {
    if (props.routine)
        form.patch(WorkoutRoutineController.update(props.routine.id).url, {
            onSuccess: () => {
                form.revision = props.routine?.revision ?? null;
                assignment.revision = props.routine?.revision ?? 1;
                form.defaults();
            },
        });
    else
        form.post(WorkoutRoutineController.store().url, {
            onSuccess: () => {
                form.revision = props.routine?.revision ?? null;
                assignment.revision = props.routine?.revision ?? 1;
                form.defaults();
            },
        });
}
function assign() {
    if (props.routine)
        assignment.post(WorkoutRoutineController.assign(props.routine.id).url, {
            onSuccess: () => assignment.reset('member_id'),
        });
}
function findMembers() {
    if (props.routine)
        router.get(
            WorkoutRoutineController.edit(props.routine.id).url,
            { search: search.value },
            { preserveState: true, preserveScroll: true },
        );
}
function removeDay() {
    form.days.splice(selectedDay.value, 1);
    selectedDay.value = Math.min(selectedDay.value, form.days.length - 1);
}
</script>
<template>
    <Head
        :title="routine ? 'Edit workout routine' : 'Create workout routine'"
    />
    <div class="max-w-4xl space-y-6 px-4 py-6 sm:px-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">
                {{
                    routine ? 'Edit workout routine' : 'Create workout routine'
                }}
            </h1>
            <Button as-child variant="outline"
                ><Link :href="WorkoutRoutineController.index()"
                    >All routines</Link
                ></Button
            >
        </div>
        <p v-if="!canWrite" role="status" class="rounded-lg border p-4">
            This branch is read-only. Activate the branch before saving or
            assigning routines.
        </p>
        <p class="text-sm text-muted-foreground">
            Use exercise instructions only. Do not enter health conditions,
            injuries, or other sensitive personal information.
        </p>
        <form class="space-y-5" @submit.prevent="save">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-2">
                    <Label for="routine-name">Routine name</Label
                    ><Input
                        id="routine-name"
                        v-model="form.name"
                        required
                        maxlength="100"
                    /><InputError :message="form.errors.name" />
                </div>
                <div class="space-y-2">
                    <Label for="routine-branch">Branch</Label>
                    <p v-if="routine" class="flex min-h-11 items-center">
                        {{ routine.branch }}
                    </p>
                    <NativeSelect
                        v-else
                        id="routine-branch"
                        :value="form.branch_id"
                        required
                        @change="
                            form.branch_id = Number(
                                ($event.target as HTMLSelectElement).value,
                            )
                        "
                        ><option value="" disabled>Choose branch</option>
                        <option
                            v-for="branch in branches?.data"
                            :key="branch.id"
                            :value="branch.id"
                            :disabled="!branch.is_active"
                        >
                            {{ branch.name
                            }}{{ branch.is_active ? '' : ' (read-only)' }}
                        </option></NativeSelect
                    ><InputError :message="form.errors.branch_id" />
                    <div v-if="branches" class="flex gap-2">
                        <Button
                            v-if="branches.prev_page_url"
                            variant="outline"
                            size="sm"
                            as-child
                            ><Link :href="branches.prev_page_url"
                                >Previous branches</Link
                            ></Button
                        ><Button
                            v-if="branches.next_page_url"
                            variant="outline"
                            size="sm"
                            as-child
                            ><Link :href="branches.next_page_url"
                                >More branches</Link
                            ></Button
                        >
                    </div>
                </div>
            </div>
            <div class="space-y-2">
                <Label for="routine-notes">Routine notes (optional)</Label
                ><Input
                    id="routine-notes"
                    v-model="form.notes"
                    maxlength="1000"
                /><InputError :message="form.errors.notes" />
            </div>
            <div class="flex flex-wrap gap-2" aria-label="Workout days">
                <Button
                    v-for="(item, i) in form.days"
                    :key="i"
                    type="button"
                    :variant="selectedDay === i ? 'default' : 'outline'"
                    :aria-pressed="selectedDay === i"
                    @click="selectedDay = i"
                    >{{ item.name || `Day ${i + 1}` }}</Button
                ><Button
                    type="button"
                    variant="outline"
                    :disabled="form.days.length >= 7"
                    @click="
                        form.days.push({
                            name: `Day ${form.days.length + 1}`,
                            exercises: [exercise()],
                        });
                        selectedDay = form.days.length - 1;
                    "
                    >Add day</Button
                >
            </div>
            <InputError :message="form.errors.days" />
            <section
                v-if="day"
                class="space-y-4 rounded-lg border p-4"
                aria-label="Selected workout day"
            >
                <div class="flex flex-wrap items-end gap-3">
                    <div class="min-w-0 flex-1 space-y-2">
                        <Label for="day-name">Day name</Label
                        ><Input
                            id="day-name"
                            v-model="day.name"
                            required
                            maxlength="100"
                        /><InputError
                            :message="error(`days.${selectedDay}.name`)"
                        />
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="form.days.length === 1"
                        @click="removeDay"
                        >Remove day</Button
                    >
                </div>
                <div
                    v-for="(item, i) in day.exercises"
                    :key="i"
                    class="space-y-3 rounded-md border p-3"
                >
                    <h3 class="font-medium">Exercise {{ i + 1 }}</h3>
                    <div class="space-y-2">
                        <Label :for="`exercise-${i}`">Exercise name</Label
                        ><Input
                            :id="`exercise-${i}`"
                            v-model="item.name"
                            required
                            maxlength="100"
                        /><InputError
                            :message="
                                error(`days.${selectedDay}.exercises.${i}.name`)
                            "
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-2">
                            <Label :for="`sets-${i}`">Sets</Label
                            ><Input
                                :id="`sets-${i}`"
                                v-model.number="item.sets"
                                type="number"
                                min="1"
                                max="100"
                                required
                            /><InputError
                                :message="
                                    error(
                                        `days.${selectedDay}.exercises.${i}.sets`,
                                    )
                                "
                            />
                        </div>
                        <div class="space-y-2">
                            <Label :for="`reps-${i}`">Reps / duration</Label
                            ><Input
                                :id="`reps-${i}`"
                                v-model="item.reps"
                                required
                                maxlength="50"
                            /><InputError
                                :message="
                                    error(
                                        `days.${selectedDay}.exercises.${i}.reps`,
                                    )
                                "
                            />
                        </div>
                    </div>
                    <div class="space-y-2">
                        <Label :for="`notes-${i}`"
                            >Exercise notes (optional)</Label
                        ><Input
                            :id="`notes-${i}`"
                            v-model="item.notes"
                            maxlength="500"
                        /><InputError
                            :message="
                                error(
                                    `days.${selectedDay}.exercises.${i}.notes`,
                                )
                            "
                        />
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="day.exercises.length === 1"
                        @click="day.exercises.splice(i, 1)"
                        >Remove exercise {{ i + 1 }}</Button
                    >
                </div>
                <InputError
                    :message="error(`days.${selectedDay}.exercises`)"
                /><Button
                    type="button"
                    variant="outline"
                    :disabled="day.exercises.length >= 20"
                    @click="day.exercises.push(exercise())"
                    >Add exercise</Button
                >
            </section>
            <div
                v-if="Object.keys(form.errors).length"
                role="alert"
                class="space-y-1 text-sm text-destructive"
            >
                <p v-for="(message, key) in form.errors" :key="key">
                    {{ message }}
                </p>
            </div>
            <Button type="submit" :disabled="form.processing || !canWrite"
                ><Spinner v-if="form.processing" />Save routine</Button
            >
        </form>
        <section
            v-if="routine"
            class="space-y-4 border-t pt-6"
            aria-labelledby="assign-title"
        >
            <h2 id="assign-title" class="text-lg font-semibold">
                Assign to a member
            </h2>
            <p class="text-sm text-muted-foreground">
                Assignments use the saved routine, version
                {{ routine.revision }}. Save changes first. Assigning replaces
                the member's current routine and preserves their history.
            </p>
            <p
                v-if="form.isDirty"
                role="status"
                class="text-sm text-muted-foreground"
            >
                Save your changes before assigning this routine.
            </p>
            <form
                class="flex flex-wrap items-end gap-3"
                @submit.prevent="findMembers"
            >
                <div class="min-w-0 flex-1 space-y-2">
                    <Label for="member-search">Search member name</Label
                    ><Input
                        id="member-search"
                        v-model="search"
                        maxlength="100"
                    />
                </div>
                <Button variant="outline" type="submit">Search</Button>
            </form>
            <form class="space-y-3" @submit.prevent="assign">
                <Label for="assign-member">Member</Label
                ><NativeSelect
                    id="assign-member"
                    :value="assignment.member_id"
                    required
                    @change="
                        assignment.member_id = (
                            $event.target as HTMLSelectElement
                        ).value
                    "
                    ><option value="" disabled>Choose an active member</option>
                    <option
                        v-for="member in members?.data"
                        :key="member.id"
                        :value="member.id"
                    >
                        {{ member.name }}
                    </option></NativeSelect
                >
                <p
                    v-if="!members?.data.length"
                    class="text-sm text-muted-foreground"
                >
                    No active members match in this branch.
                </p>
                <div v-if="members" class="flex gap-2">
                    <Button
                        v-if="members.prev_page_url"
                        as-child
                        variant="outline"
                        ><Link
                            :href="members.prev_page_url"
                            preserve-state
                            preserve-scroll
                            >Previous members</Link
                        ></Button
                    ><Button
                        v-if="members.next_page_url"
                        as-child
                        variant="outline"
                        ><Link
                            :href="members.next_page_url"
                            preserve-state
                            preserve-scroll
                            >More members</Link
                        ></Button
                    >
                </div>
                <div role="alert">
                    <InputError
                        v-for="(message, key) in assignment.errors"
                        :key="key"
                        :message="message"
                    />
                </div>
                <Button
                    type="submit"
                    :disabled="
                        assignment.processing ||
                        !assignment.member_id ||
                        !canWrite ||
                        form.isDirty
                    "
                    ><Spinner v-if="assignment.processing" />Assign
                    routine</Button
                >
            </form>
            <h3 class="font-medium">Current assignments</h3>
            <p
                v-if="!assignments?.data.length"
                class="text-sm text-muted-foreground"
            >
                No members are currently assigned this routine.
            </p>
            <RecordList
                v-if="assignments?.data.length"
                :rows="assignments.data"
                :columns="[
                    { key: 'member', label: 'Member' },
                    { key: 'revision', label: 'Version' },
                    { key: 'assignedAt', label: 'Assigned (Manila)' },
                ]"
                caption="Current routine assignments"
                row-key="id"
                ><template #cell-member="{ row: item }"
                    ><p class="font-medium">{{ item.member }}</p></template
                ><template #cell-revision="{ row: item }"
                    >{{ item.revision }}
                    <p
                        v-if="item.revision < routine.revision"
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        Reassign to use the latest saved version
                    </p></template
                ><template #cell-assignedAt="{ row: item }">{{
                    formatDisplayDate(item.assignedAt)
                }}</template></RecordList
            >
            <div v-if="assignments" class="flex gap-2">
                <Button
                    v-if="assignments.prev_page_url"
                    as-child
                    variant="outline"
                    ><Link
                        :href="assignments.prev_page_url"
                        preserve-state
                        preserve-scroll
                        >Previous assignments</Link
                    ></Button
                ><Button
                    v-if="assignments.next_page_url"
                    as-child
                    variant="outline"
                    ><Link
                        :href="assignments.next_page_url"
                        preserve-state
                        preserve-scroll
                        >More assignments</Link
                    ></Button
                >
            </div>
        </section>
    </div>
</template>
