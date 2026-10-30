<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    BarChart3,
    Bell,
    CalendarRange,
    CircleHelp,
    FilePlus2,
    FileSpreadsheet,
    Gauge,
    Inbox,
    LayoutDashboard,
    ListChecks,
    PenLine,
    Settings2,
    ShieldCheck,
    UserRoundCheck,
    Users,
} from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const page = usePage<SharedData>();
const mainNavItems: NavItem[] = [
    {
        title: 'Tableau de bord',
        href: '/dashboard',
        icon: LayoutDashboard,
    },
    {
        title: 'Bilan trimestriel',
        href: '/pilotage',
        icon: CalendarRange,
    },
    {
        title: 'Courriers',
        href: '/courriers',
        icon: Inbox,
    },
];

if (['admin', 'director', 'secretariat'].includes(page.props.auth.user.role)) {
    mainNavItems.push({ title: 'Nouveau courrier', href: '/courriers/create', icon: FilePlus2 });
}
if (['admin', 'director', 'division_head'].includes(page.props.auth.user.role)) {
    mainNavItems.push({ title: 'Imputation en masse', href: '/courriers/bulk-assignment', icon: ListChecks });
}
mainNavItems.push({
    title: 'Notifications',
    href: '/notifications',
    icon: Bell,
    badge: page.props.notifications.unread_count,
});
if (page.props.auth.user.role !== 'agent') {
    mainNavItems.push({ title: 'Rapports', href: '/reports', icon: BarChart3 });
    mainNavItems.push({ title: 'Performance', href: '/reports/performance', icon: Gauge });
}
const adminNavItems: NavItem[] = [];
const supportNavItems: NavItem[] = [{ title: 'Aide & guide', href: '/aide', icon: CircleHelp }];
const workNavItems: NavItem[] = [];
const role = page.props.auth.user.role;
const counts = page.props.navigation;

if (['admin', 'director', 'secretariat'].includes(role)) {
    workNavItems.push({ title: 'Sans division', href: '/courriers?queue=unassigned', icon: Inbox, badge: counts.unassigned });
}
if (role === 'division_head') {
    workNavItems.push(
        { title: 'Sans agent', href: '/courriers?queue=division_only', icon: UserRoundCheck, badge: counts.division_only },
        { title: 'À valider', href: '/courriers?queue=review', icon: ShieldCheck, badge: counts.review },
    );
}
if (role === 'agent') {
    workNavItems.push(
        { title: 'Mes courriers', href: '/courriers?queue=mine', icon: UserRoundCheck, badge: counts.mine },
        { title: 'À corriger', href: '/courriers?queue=corrections', icon: PenLine, badge: counts.corrections },
    );
}
if (['admin', 'director'].includes(role)) {
    workNavItems.push({ title: 'À signer', href: '/courriers?queue=signature', icon: ShieldCheck, badge: counts.signature });
}
workNavItems.push({ title: 'Priorités', href: '/courriers?queue=priority', icon: AlertTriangle, badge: counts.priority });

if (page.props.auth.user.role === 'admin') {
    adminNavItems.push(
        { title: 'Utilisateurs', href: '/administration/users', icon: Users },
        { title: 'Paramètres DISC', href: '/administration/settings', icon: Settings2 },
        { title: 'Import Excel', href: '/administration/imports', icon: FileSpreadsheet },
    );
}
</script>

<template>
    <Sidebar collapsible="icon" variant="inset" class="disc-sidebar">
        <SidebarHeader class="px-3 pb-2 pt-4">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child class="h-14 rounded-2xl hover:bg-white/5">
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain label="Espace de travail" :items="mainNavItems" />
            <NavMain v-if="workNavItems.length" label="Mes files" :items="workNavItems" />
            <NavMain v-if="adminNavItems.length" label="Administration" :items="adminNavItems" />
            <NavMain label="Assistance" :items="supportNavItems" />
        </SidebarContent>

        <SidebarFooter class="p-3">
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
