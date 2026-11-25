<script setup lang="ts">
import { formatDisplayDate } from '@/lib/date-format';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { portalUrl } from '@/lib/portal-route';
import { logout, workouts } from '@/routes/portal';
const props = defineProps<{
    gymName: string;
    gymSlug: string;
    memberName: string;
    routine: {
        name: string;
        notes: string;
        version: number;
        assignedAt: string;
        days: string[];
        selectedDay: number;
    } | null;
    exercises: {
        data: { name: string; sets: number; reps: string; notes: string }[];
        current_page: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    } | null;
}>();
const busy = ref(false);
function signOut() {
    busy.value = true;
    router.post(
        portalUrl(logout(props.gymSlug)),
        {},
        {
            onFinish: () => {
                busy.value = false;
            },
        },
    );
}
</script>
<template>
    <Head title="My workout"
        ><meta name="robots" content="noindex, nofollow"
    /></Head>
    <div class="space-y-6">
        <header class="flex flex-wrap items-center justify-between gap-4">
            <div class="min-w-0">
                <h1 class="text-xl font-semibold">My workout</h1>
                <p class="mt-2 break-words">{{ memberName }}</p>
            </div>
            <Button variant="outline" :disabled="busy" @click="signOut"
                ><Spinner v-if="busy" />Sign out</Button
            >
        </header>
        <p v-if="!routine" class="rounded-xl border p-6">
            No workout routine has been assigned yet. Ask your coach or the
            front desk to assign one.
        </p>
        <template v-else>
            <section class="space-y-3 rounded-xl border bg-card p-6">
                <h2 class="text-lg font-semibold break-words">
                    {{ routine.name }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    Version {{ routine.version }} · Assigned
                    {{ formatDisplayDate(routine.assignedAt) }} (Manila time)
                </p>
                <p
                    v-if="routine.notes"
                    class="text-sm break-words whitespace-pre-wrap"
                >
                    {{ routine.notes }}
                </p>
            </section>
            <nav aria-label="Workout days" class="flex flex-wrap gap-2">
                <Button
                    v-for="(day, i) in routine.days"
                    :key="i"
                    as-child
                    :variant="
                        routine.selectedDay === i + 1 ? 'default' : 'outline'
                    "
                    class="h-auto min-h-11 max-w-full whitespace-normal"
                    ><Link
                        :href="
                            portalUrl(
                                workouts(gymSlug, { query: { day: i + 1 } }),
                            )
                        "
                        :aria-current="
                            routine.selectedDay === i + 1 ? 'page' : undefined
                        "
                        >{{ day }}</Link
                    ></Button
                >
            </nav>
            <section
                class="space-y-4"
                :aria-label="`${routine.days[routine.selectedDay - 1]} exercises`"
            >
                <h2 class="text-lg font-semibold break-words">
                    {{ routine.days[routine.selectedDay - 1] }}
                </h2>
                <p
                    v-if="!exercises?.data.length"
                    class="text-sm text-muted-foreground"
                >
                    No exercises on this page. Choose a workout day or return to
                    the previous page.
                </p>
                <article
                    v-for="(item, i) in exercises?.data"
                    :key="i"
                    class="space-y-3 rounded-xl border bg-card p-6"
                >
                    <h3 class="font-semibold break-words">{{ item.name }}</h3>
                    <dl class="grid grid-cols-2 gap-4">
                        <div>
                            <dt class="text-sm text-muted-foreground">Sets</dt>
                            <dd class="mt-1 font-medium">{{ item.sets }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-muted-foreground">
                                Reps / duration
                            </dt>
                            <dd class="mt-1 font-medium break-words">
                                {{ item.reps }}
                            </dd>
                        </div>
                    </dl>
                    <p
                        v-if="item.notes"
                        class="text-sm break-words whitespace-pre-wrap"
                    >
                        {{ item.notes }}
                    </p>
                </article>
            </section>
            <nav
                v-if="
                    exercises &&
                    (exercises.prev_page_url || exercises.next_page_url)
                "
                aria-label="Exercise pages"
                class="flex flex-wrap gap-3"
            >
                <Button
                    v-if="exercises.prev_page_url"
                    variant="outline"
                    as-child
                    ><Link :href="exercises.prev_page_url"
                        >Previous exercises</Link
                    ></Button
                ><Button
                    v-if="exercises.next_page_url"
                    variant="outline"
                    as-child
                    ><Link :href="exercises.next_page_url"
                        >More exercises</Link
                    ></Button
                >
            </nav>
            <p class="text-sm text-muted-foreground">
                This is the saved routine your gym assigned to you. Ask your
                coach if you need an updated version.
            </p>
        </template>
    </div>
</template>
