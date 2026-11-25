<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Dumbbell, Home, Search, UserRound } from '@lucide/vue';
import { calorie, check, home, login, workouts } from '@/routes/portal';
import { computed } from 'vue';
import { portalUrl } from '@/lib/portal-route';
const page = usePage<{
    gymName: string;
    gymSlug: string;
    memberAuthAvailable?: boolean;
    homeUrl?: string | null;
    logoUrl?: string | null;
    imageUrl?: string | null;
    workoutsEnabled?: boolean;
    calorieHelperAvailable?: boolean;
}>();
const checkPage = computed(() => page.component === 'portal/CheckMembership');
const memberPage = computed(() =>
    ['portal/Membership', 'portal/Workouts', 'portal/CalorieHelper'].includes(
        page.component,
    ),
);
</script>
<template>
    <div
        :class="!memberPage ? 'dark' : ''"
        class="relative isolate min-h-screen bg-background text-foreground"
    >
        <div
            v-if="checkPage"
            aria-hidden="true"
            class="absolute inset-0 -z-10 overflow-hidden bg-background"
        >
            <img
                v-if="page.props.imageUrl"
                :src="page.props.imageUrl"
                alt=""
                class="h-full w-full object-cover opacity-55"
            />
            <div
                class="absolute inset-0 bg-linear-to-r from-background via-background/85 to-background/30"
            ></div>
            <div
                class="absolute inset-0 bg-linear-to-br from-primary/10 via-transparent to-primary/5"
            ></div>
        </div>
        <a
            href="#main-content"
            class="sr-only z-50 rounded-md bg-primary px-4 py-3 text-primary-foreground focus:not-sr-only focus:fixed focus:top-2 focus:left-2"
            >Skip to main content</a
        >
        <header
            class="border-b"
            :class="
                checkPage
                    ? 'border-white/10 bg-background/80 backdrop-blur'
                    : ''
            "
        >
            <div
                v-if="checkPage"
                class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-6 lg:px-8"
            >
                <a
                    v-if="page.props.homeUrl"
                    :href="page.props.homeUrl"
                    class="flex min-h-11 min-w-0 items-center gap-3 rounded-md focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring"
                >
                    <img
                        v-if="page.props.logoUrl"
                        :src="page.props.logoUrl"
                        alt=""
                        width="44"
                        height="44"
                        class="size-11 shrink-0 rounded-lg object-contain"
                    />
                    <Dumbbell
                        v-else
                        class="size-11 shrink-0 text-red-500"
                        aria-hidden="true"
                    />
                    <span class="min-w-0"
                        ><span
                            class="block text-base font-bold break-words sm:text-xl"
                            >{{ page.props.gymName }}</span
                        ><span
                            class="mt-1 block text-xs tracking-[0.2em] text-muted-foreground uppercase"
                            >Member portal</span
                        ></span
                    >
                </a>
                <p
                    v-else
                    class="flex min-w-0 items-center gap-3 font-bold break-words"
                >
                    <Dumbbell
                        class="size-11 shrink-0 text-red-500"
                        aria-hidden="true"
                    />{{ page.props.gymName }}
                </p>
                <nav
                    aria-label="Member portal tabs"
                    class="flex flex-wrap items-center gap-1 sm:gap-2"
                >
                    <a
                        v-if="page.props.homeUrl"
                        :href="page.props.homeUrl"
                        class="inline-flex min-h-11 items-center gap-2 rounded-lg px-3 text-sm font-medium hover:bg-white/5 focus-visible:outline-2 focus-visible:outline-ring"
                        ><Home class="size-5" aria-hidden="true" />Home</a
                    >
                    <Link
                        v-if="page.props.memberAuthAvailable"
                        :href="portalUrl(login(page.props.gymSlug))"
                        class="inline-flex min-h-11 items-center gap-2 rounded-lg px-3 text-sm font-medium hover:bg-white/5 focus-visible:outline-2 focus-visible:outline-ring"
                        ><UserRound class="size-5" aria-hidden="true" />Member
                        sign-in</Link
                    >
                    <Link
                        :href="portalUrl(check(page.props.gymSlug))"
                        aria-current="page"
                        class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-primary/40 bg-primary/20 px-4 text-sm font-semibold hover:bg-primary/30 focus-visible:outline-2 focus-visible:outline-ring"
                        ><Search class="size-5" aria-hidden="true" />Check
                        membership</Link
                    >
                </nav>
            </div>
            <div v-else class="mx-auto max-w-3xl px-4 py-5 sm:px-6">
                <p class="font-semibold break-words">
                    {{ page.props.gymName }}
                </p>
                <p class="text-sm text-muted-foreground">Member portal</p>
                <nav
                    aria-label="Member portal tabs"
                    class="mt-4 flex flex-wrap gap-2"
                >
                    <Link
                        v-if="memberPage && page.props.calorieHelperAvailable"
                        :href="portalUrl(calorie(page.props.gymSlug))"
                        class="inline-flex min-h-11 items-center rounded-md border px-4"
                        :aria-current="
                            page.component === 'portal/CalorieHelper'
                                ? 'page'
                                : undefined
                        "
                        >Calorie &amp; goal helper</Link
                    >
                    <Link
                        :href="
                            portalUrl(
                                memberPage
                                    ? home(page.props.gymSlug)
                                    : login(page.props.gymSlug),
                            )
                        "
                        class="inline-flex min-h-11 items-center rounded-md border px-4"
                        :aria-current="
                            ['portal/Login', 'portal/Membership'].includes(
                                page.component,
                            )
                                ? 'page'
                                : undefined
                        "
                        >{{
                            memberPage ? 'My membership' : 'Member sign-in'
                        }}</Link
                    >
                    <Link
                        v-if="memberPage && page.props.workoutsEnabled"
                        :href="portalUrl(workouts(page.props.gymSlug))"
                        class="inline-flex min-h-11 items-center rounded-md border px-4"
                        :class="
                            page.component === 'portal/Workouts'
                                ? 'bg-secondary'
                                : ''
                        "
                        :aria-current="
                            page.component === 'portal/Workouts'
                                ? 'page'
                                : undefined
                        "
                        >My workout</Link
                    >
                    <Link
                        :href="portalUrl(check(page.props.gymSlug))"
                        class="inline-flex min-h-11 items-center rounded-md border px-4"
                        :class="
                            page.component === 'portal/CheckMembership'
                                ? 'bg-secondary'
                                : ''
                        "
                        :aria-current="
                            page.component === 'portal/CheckMembership'
                                ? 'page'
                                : undefined
                        "
                        >Check membership</Link
                    >
                </nav>
            </div>
        </header>
        <main
            id="main-content"
            tabindex="-1"
            class="mx-auto"
            :class="
                checkPage
                    ? 'max-w-7xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8 short:py-6'
                    : 'max-w-3xl px-4 py-8 sm:px-6'
            "
        >
            <slot />
        </main>
    </div>
</template>
