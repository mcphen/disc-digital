<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <div class="disc-app-backdrop">
        <div class="pointer-events-none fixed inset-x-0 top-0 z-50 grid h-1 grid-cols-3">
            <span class="bg-[#00853f]" /><span class="bg-[#fdef42]" /><span class="bg-[#e31b23]" />
        </div>
        <div class="disc-orb -left-24 top-10 size-72 bg-amber-300/25 blur-3xl dark:bg-amber-600/10" />
        <div class="disc-orb -right-24 top-1/3 size-96 bg-emerald-300/15 blur-3xl dark:bg-emerald-600/10" style="animation-delay: -8s" />
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" class="disc-main-surface">
                <AppSidebarHeader :breadcrumbs="breadcrumbs" />
                <div class="disc-page-shell disc-page-enter flex min-h-0 flex-1 flex-col">
                    <slot />
                </div>
            </AppContent>
        </AppShell>
    </div>
</template>
