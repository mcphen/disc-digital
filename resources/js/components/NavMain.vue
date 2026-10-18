<script setup lang="ts">
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuBadge, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';

defineProps<{
    items: NavItem[];
    label?: string;
}>();

const page = usePage<SharedData>();
const isActive = (href: string) => {
    const [currentPath, currentQuery = ''] = page.url.split('?');
    const [targetPath, targetQuery = ''] = href.split('?');

    if (currentPath !== targetPath) {
        return href === '/courriers' && /^\/courriers\/\d+/.test(currentPath);
    }

    if (targetQuery) {
        const current = new URLSearchParams(currentQuery);
        const target = new URLSearchParams(targetQuery);

        return [...target.entries()].every(([key, value]) => current.get(key) === value);
    }

    return !currentQuery || !new URLSearchParams(currentQuery).has('queue');
};
</script>

<template>
    <SidebarGroup class="px-3 py-3">
        <SidebarGroupLabel class="mb-2 px-3 text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500">{{
            label ?? 'Navigation'
        }}</SidebarGroupLabel>
        <SidebarMenu class="gap-1.5">
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :tooltip="item.title"
                    :is-active="isActive(item.href)"
                    class="h-11 rounded-xl px-3 text-stone-300 transition-all duration-300 hover:bg-white/[0.08] hover:text-white data-[active=true]:bg-amber-400/10 data-[active=true]:font-semibold data-[active=true]:text-amber-300 data-[active=true]:shadow-[inset_3px_0_0_0_#d6a329]"
                >
                    <Link :href="item.href">
                        <component :is="item.icon" class="size-[18px]" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
                <SidebarMenuBadge
                    v-if="item.badge"
                    class="right-2 bg-white/10 text-[10px] font-semibold text-stone-200 group-data-[collapsible=icon]:hidden"
                >
                    {{ item.badge > 99 ? '99+' : item.badge }}
                </SidebarMenuBadge>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
