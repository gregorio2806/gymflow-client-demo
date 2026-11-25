<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Building2,
    CalendarDays,
    Package,
    ClipboardList,
    LayoutGrid,
    MailPlus,
    ShieldCheck,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as attendanceIndex } from '@/routes/attendance';
import { index as checkInIndex } from '@/routes/check-in';
import { index as branchesIndex } from '@/routes/branches';
import { edit as websiteEdit } from '@/routes/website';
import { index as platformGyms } from '@/routes/platform/gyms';
import { index as ownerInvitations } from '@/routes/platform/owner-invitations';
import { index as staffIndex } from '@/routes/staff';
import { index as membersIndex } from '@/routes/members';
import { index as freezeRecordsIndex } from '@/routes/freeze-records';
import { index as membershipPlansIndex } from '@/routes/membership-plans';
import { index as workoutsIndex } from '@/routes/workout-routines';
import { index as reportsIndex } from '@/routes/reports';
import { index as appointmentsIndex } from '@/routes/appointments';
import { roster as coachCalendarRoster } from '@/routes/coach-calendar';
import { index as productInventoryIndex } from '@/routes/product-inventory';
import type { NavItem } from '@/types';

const page = usePage();

const mainNavItems = computed<NavItem[]>(() => [
    ...(page.props.features.includes('coach-calendar') &&
    ['owner', 'staff', 'branch-manager', 'coach'].includes(
        page.props.currentGym?.role ?? '',
    )
        ? [
              {
                  title: 'Appointments',
                  href: appointmentsIndex(),
                  icon: CalendarDays,
              },
              {
                  title: 'Coach calendar',
                  href: coachCalendarRoster(),
                  icon: CalendarDays,
              },
          ]
        : []),
    ...(page.props.features.includes('reports') &&
    ['owner', 'staff', 'branch-manager'].includes(
        page.props.currentGym?.role ?? '',
    )
        ? [
              {
                  title: 'Reports',
                  href: reportsIndex(),
                  icon: ClipboardList,
                  children: [
                      ...(page.props.features.includes('payments')
                          ? ['Revenue']
                          : []),
                      ...(page.props.features.includes('members')
                          ? ['Membership & Payments']
                          : []),
                      ...(page.props.features.includes('qr-attendance')
                          ? ['Attendance']
                          : []),
                      ...(page.props.features.includes('coach-calendar')
                          ? ['Appointments']
                          : []),
                  ]
                      .sort(
                          (a, b) =>
                              [
                                  'Revenue',
                                  'Membership & Payments',
                                  'Attendance',
                                  'Appointments',
                              ].indexOf(a) -
                              [
                                  'Revenue',
                                  'Membership & Payments',
                                  'Attendance',
                                  'Appointments',
                              ].indexOf(b),
                      )
                      .map((title) => ({
                          title,
                          href: reportsIndex({
                              query: {
                                  report:
                                      title === 'Membership & Payments'
                                          ? 'membership'
                                          : title.toLowerCase(),
                              },
                          }),
                      })),
              },
          ]
        : []),
    ...(page.props.features.includes('workouts') &&
    ['owner', 'staff', 'branch-manager', 'coach'].includes(
        page.props.currentGym?.role ?? '',
    )
        ? [{ title: 'Workouts', href: workoutsIndex(), icon: ClipboardList }]
        : []),
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    ...(page.props.features.includes('qr-attendance') &&
    ['owner', 'staff', 'branch-manager'].includes(
        page.props.currentGym?.role ?? '',
    )
        ? [
              {
                  title: 'Check-in',
                  href: checkInIndex(),
                  icon: ClipboardList,
              },
              {
                  title: 'Attendance History',
                  href: attendanceIndex(),
                  icon: ClipboardList,
              },
          ]
        : []),
    ...(page.props.features.includes('members') &&
    ['owner', 'staff', 'branch-manager'].includes(
        page.props.currentGym?.role ?? '',
    )
        ? [
              { title: 'Members', href: membersIndex(), icon: Users },
              {
                  title: 'Freeze records',
                  href: freezeRecordsIndex(),
                  icon: ClipboardList,
              },
              {
                  title: 'Plans & Pricing',
                  href: membershipPlansIndex(),
                  icon: ClipboardList,
              },
          ]
        : []),
    // Owner-only (the server also blocks non-owners)
    ...(page.props.currentGym?.role === 'owner'
        ? [
              { title: 'Staff/Coaches', href: staffIndex(), icon: Users },
              ...(page.props.currentGym?.plan === 'multi_branch' ||
              page.props.currentGym?.hasExtraBranches
                  ? [
                        {
                            title: 'Branches',
                            href: branchesIndex(),
                            icon: Building2,
                        },
                    ]
                  : []),
              ...(page.props.features.includes('website')
                  ? [
                        {
                            title: 'Gym website',
                            href: websiteEdit(),
                            icon: Building2,
                        },
                    ]
                  : []),
          ]
        : []),
    // Platform admin ("super admin") only — the server also blocks everyone else
    ...(page.props.currentGym?.role === 'branch-manager'
        ? [
              { title: 'Branches', href: branchesIndex(), icon: Building2 },
              { title: 'Staff/Coaches', href: staffIndex(), icon: Users },
          ]
        : []),
    ...(page.props.isPlatformAdmin
        ? [
              {
                  title: 'Platform: gyms',
                  href: platformGyms(),
                  icon: ShieldCheck,
              },
              {
                  title: 'Platform: invite owners',
                  href: ownerInvitations(),
                  icon: MailPlus,
              },
          ]
        : []),
]);

const groupedNavItems = computed<NavItem[]>(() => {
    const items = [...mainNavItems.value];
    const find = (title: string) => items.find((item) => item.title === title);
    const group = (
        title: string,
        icon: NavItem['icon'],
        names: string[],
    ): NavItem[] => {
        const children = names.flatMap((name) =>
            find(name) ? [find(name)!] : [],
        );
        return children.length
            ? [{ title, icon, href: children[0].href, children }]
            : [];
    };
    return [
        find('Dashboard')!,
        ...group('Attendance', ClipboardList, [
            'Check-in',
            'Attendance History',
        ]),
        ...(page.props.currentGym && page.props.currentGym.role !== 'coach'
            ? [
                  {
                      title: 'Product inventory',
                      href: productInventoryIndex(),
                      icon: Package,
                  },
              ]
            : []),
        ...group('Appointment', CalendarDays, [
            'Appointments',
            'Coach calendar',
        ]),
        ...group('Members', Users, ['Members', 'Freeze records']),
        ...(find('Plans & Pricing')
            ? [
                  {
                      ...find('Plans & Pricing')!,
                      children: [
                          {
                              title: 'Gym Access Plans',
                              href: membershipPlansIndex({
                                  query: { tab: 'subscription' },
                              }),
                          },
                          {
                              title: 'Membership plans',
                              href: membershipPlansIndex({
                                  query: { tab: 'benefit' },
                              }),
                          },
                          {
                              title: 'Discounts',
                              href: membershipPlansIndex({
                                  query: { tab: 'discounts' },
                              }),
                          },
                          {
                              title: 'Training packages',
                              href: membershipPlansIndex({
                                  query: { tab: 'package' },
                              }),
                          },
                      ],
                  },
              ]
            : []),
        ...(find('Reports') ? [find('Reports')!] : []),
        ...(find('Gym website')
            ? [{ ...find('Gym website')!, title: 'Website' }]
            : []),
        ...['Workouts'].flatMap((name) => (find(name) ? [find(name)!] : [])),
        ...group('Users', Users, ['Staff/Coaches']),
        ...['Branches'].flatMap((name) => (find(name) ? [find(name)!] : [])),
        ...items.filter((item) => item.title.startsWith('Platform:')),
    ];
});
</script>

<template>
    <Sidebar
        collapsible="icon"
        variant="inset"
        role="complementary"
        aria-label="Gym navigation"
    >
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="groupedNavItems" />
        </SidebarContent>
    </Sidebar>
    <slot />
</template>
