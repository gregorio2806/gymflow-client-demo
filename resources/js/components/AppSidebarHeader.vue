<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import {
    Bell,
    Building2,
    Check,
    ChevronDown,
    Moon,
    Sun,
    UserRound,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuTrigger,
    DropdownMenuItem,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useAppearance } from '@/composables/useAppearance';
import { update as updateBranchView } from '@/routes/branch-view';
import type { BreadcrumbItem } from '@/types';
withDefaults(defineProps<{ breadcrumbs?: BreadcrumbItem[] }>(), {
    breadcrumbs: () => [],
});
const page = usePage();
const { resolvedAppearance, updateAppearance } = useAppearance();
const switching = ref(false);
const branchError = ref('');
const branchId = computed(() => page.props.selectedBranchId);
const branchName = computed(
    () =>
        page.props.navigationBranches?.find(
            (branch) => branch.id === branchId.value,
        )?.name ?? 'All branches',
);
function switchBranch(id: number | null) {
    if (switching.value || id === branchId.value) return;
    const url = new URL(page.url, window.location.origin);
    url.searchParams.delete('branch_id');
    const paginationKeys = Array.from(url.searchParams.keys()).filter(
        (key) => key === 'page' || key.endsWith('_page'),
    );
    for (const key of paginationKeys) url.searchParams.delete(key);
    branchError.value = '';
    switching.value = true;
    router.put(
        updateBranchView(),
        { branch_id: id, return_to: url.pathname + url.search },
        {
            preserveScroll: true,
            preserveState: false,
            onError: () => {
                branchError.value = 'Could not switch branch. Try again.';
            },
            onFinish: () => {
                switching.value = false;
            },
        },
    );
}
</script>
<template>
    <header
        class="flex min-h-16 shrink-0 flex-wrap items-center justify-between gap-2 border-b border-sidebar-border/70 bg-background px-4 py-2 max-md:sticky max-md:top-0 max-md:z-30"
    >
        <div class="flex min-w-0 items-center gap-2">
            <SidebarTrigger class="size-11" />
            <div class="hidden sm:block">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </div>
        </div>
        <nav
            aria-label="Account navigation"
            class="flex min-w-0 items-center gap-1"
        >
            <DropdownMenu
                v-if="
                    page.props.currentGym &&
                    page.props.navigationBranches.length > 0
                "
            >
                <DropdownMenuTrigger as-child
                    ><Button
                        variant="outline"
                        class="max-w-32 px-2 sm:max-w-56"
                        :disabled="switching"
                        aria-label="Select branch"
                        ><Building2 class="size-5 shrink-0" /><span
                            class="truncate"
                            >{{ branchName }}</span
                        ><ChevronDown
                            class="ml-auto size-4 shrink-0"
                            aria-hidden="true" /></Button
                ></DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="max-w-72">
                    <DropdownMenuLabel
                        >View records by branch</DropdownMenuLabel
                    >
                    <DropdownMenuItem
                        v-if="!page.props.currentBranch"
                        class="min-h-11"
                        :disabled="switching"
                        @select="switchBranch(null)"
                        >All branches<Check
                            v-if="branchId === null"
                            class="ml-auto size-4"
                    /></DropdownMenuItem>
                    <DropdownMenuItem
                        v-for="branch in page.props.navigationBranches"
                        :key="branch.id"
                        class="min-h-11"
                        :disabled="switching || !!page.props.currentBranch"
                        @select="switchBranch(branch.id)"
                        >{{ branch.name
                        }}<Check
                            v-if="branchId === branch.id"
                            class="ml-auto size-4"
                    /></DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
            <p v-if="branchError" role="alert" class="text-xs text-destructive">
                {{ branchError }}
            </p>
            <DropdownMenu>
                <DropdownMenuTrigger as-child
                    ><Button
                        variant="ghost"
                        size="icon"
                        aria-label="Notifications"
                        ><Bell class="size-5" /></Button
                ></DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-64"
                    ><DropdownMenuLabel>Notifications</DropdownMenuLabel>
                    <p class="px-2 py-3 text-sm text-muted-foreground">
                        Notifications will be available in a future update.
                    </p></DropdownMenuContent
                >
            </DropdownMenu>
            <Button
                variant="ghost"
                size="icon"
                :aria-label="
                    resolvedAppearance === 'dark'
                        ? 'Switch to light mode'
                        : 'Switch to dark mode'
                "
                @click="
                    updateAppearance(
                        resolvedAppearance === 'dark' ? 'light' : 'dark',
                    )
                "
                ><Sun
                    v-if="resolvedAppearance === 'dark'"
                    class="size-5" /><Moon v-else class="size-5"
            /></Button>
            <DropdownMenu>
                <DropdownMenuTrigger as-child
                    ><Button
                        variant="ghost"
                        size="icon"
                        aria-label="Profile menu"
                        ><UserRound class="size-5" /></Button
                ></DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-64"
                    ><UserMenuContent :user="page.props.auth.user"
                /></DropdownMenuContent>
            </DropdownMenu>
        </nav>
    </header>
</template>
