<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';
import { ref, watch } from 'vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubItem,
    SidebarMenuSubButton,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';
const props = defineProps<{ items: NavItem[] }>();
const { isCurrentUrl, isCurrentOrParentUrl, currentUrl } = useCurrentUrl();
const { state, setOpen, setOpenMobile, isMobile } = useSidebar();
const expanded = ref<Record<string, boolean>>({});
const page = usePage();
const active = (item: NavItem): boolean => {
    if (item.disabled) return false;
    const target = new URL(toUrl(item.href), 'http://localhost');
    const current = new URL(page.url, 'http://localhost');
    const memberHistory = /^\/members\/[^/]+\/payments$/.test(current.pathname);
    if (target.pathname === '/payments' && memberHistory) return true;
    if (!isCurrentOrParentUrl(target.pathname)) return false;
    if (
        target.pathname === '/membership-plans' &&
        target.searchParams.has('tab')
    ) {
        const selectedTab =
            current.searchParams.get('tab') ||
            (page.props.kind as string | undefined) ||
            'subscription';
        const tab = selectedTab === 'overview' ? 'subscription' : selectedTab;
        return target.searchParams.get('tab') === tab;
    }
    if (target.pathname === '/reports' && target.searchParams.has('report')) {
        const report =
            current.searchParams.get('report') ||
            (page.props.features.includes('payments')
                ? 'revenue'
                : page.props.features.includes('members')
                  ? 'membership'
                  : page.props.features.includes('qr-attendance')
                    ? 'attendance'
                    : page.props.features.includes('coach-calendar')
                      ? 'appointments'
                      : 'revenue');
        return (
            target.searchParams.get('report') ===
            (report === 'payments' ? 'membership' : report)
        );
    }
    if (target.search) {
        return Array.from(target.searchParams).every(
            ([key, value]) => current.searchParams.get(key) === value,
        );
    }
    return (
        target.pathname !== '/members' ||
        (!memberHistory && current.pathname !== '/members/freeze-records')
    );
};
watch(
    [currentUrl, () => page.url, () => props.items],
    () => {
        for (const item of props.items) {
            if (item.children?.some(active)) expanded.value[item.title] = true;
        }
    },
    { immediate: true },
);
function toggle(item: NavItem) {
    if (state.value === 'collapsed') setOpen(true);
    expanded.value[item.title] = !expanded.value[item.title];
}
function closeMobile() {
    if (isMobile.value) setOpenMobile(false);
}
</script>
<template>
    <SidebarGroup class="px-2 py-0">
        <nav aria-label="Dashboard navigation">
            <SidebarMenu>
                <SidebarMenuItem v-for="item in items" :key="item.title">
                    <Collapsible
                        v-if="item.children"
                        :open="!!expanded[item.title]"
                        @update:open="toggle(item)"
                    >
                        <CollapsibleTrigger as-child>
                            <SidebarMenuButton
                                class="min-h-11"
                                :tooltip="item.title"
                                :is-active="item.children.some(active)"
                            >
                                <component :is="item.icon" />
                                <span>{{ item.title }}</span>
                                <ChevronDown
                                    class="ml-auto size-4 transition-transform duration-200 ease-out motion-reduce:transition-none"
                                    :class="{
                                        'rotate-180': expanded[item.title],
                                    }"
                                />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>
                        <CollapsibleContent class="sidebar-dropdown">
                            <SidebarMenuSub>
                                <SidebarMenuSubItem
                                    v-for="child in item.children"
                                    :key="child.title"
                                >
                                    <span
                                        v-if="child.disabled"
                                        class="flex min-h-11 items-center px-2 text-xs text-muted-foreground"
                                        >{{ child.title }}</span
                                    >
                                    <SidebarMenuSubButton
                                        v-else
                                        as-child
                                        class="min-h-11"
                                        :is-active="active(child)"
                                    >
                                        <Link
                                            :href="child.href"
                                            :aria-current="
                                                active(child)
                                                    ? 'page'
                                                    : undefined
                                            "
                                            @click="closeMobile"
                                            >{{ child.title }}</Link
                                        >
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>
                            </SidebarMenuSub>
                        </CollapsibleContent>
                    </Collapsible>
                    <SidebarMenuButton
                        v-else
                        as-child
                        class="min-h-11"
                        :is-active="isCurrentUrl(item.href)"
                        :tooltip="item.title"
                    >
                        <Link
                            :href="item.href"
                            :aria-current="
                                isCurrentUrl(item.href) ? 'page' : undefined
                            "
                            @click="closeMobile"
                        >
                            <component :is="item.icon" /><span>{{
                                item.title
                            }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </nav>
    </SidebarGroup>
</template>

<style scoped>
.sidebar-dropdown {
    overflow: hidden;
}

.sidebar-dropdown[data-state='open'] {
    animation: sidebar-slide-down 200ms ease-out;
}

.sidebar-dropdown[data-state='closed'] {
    animation: sidebar-slide-up 200ms ease-out;
}

@keyframes sidebar-slide-down {
    from {
        height: 0;
    }
    to {
        height: var(--reka-collapsible-content-height);
    }
}

@keyframes sidebar-slide-up {
    from {
        height: var(--reka-collapsible-content-height);
    }
    to {
        height: 0;
    }
}

@media (prefers-reduced-motion: reduce) {
    .sidebar-dropdown[data-state] {
        animation: none;
    }
}
</style>
