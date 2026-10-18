<script setup lang="ts">
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';
import { SidebarTrigger } from '@/components/ui/sidebar';
import GlobalSearch from '@/components/GlobalSearch.vue';
import type { BreadcrumbItemType, SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Bell, CalendarDays } from 'lucide-vue-next';

withDefaults(defineProps<{ breadcrumbs?: BreadcrumbItemType[] }>(), { breadcrumbs: () => [] });
const page = usePage<SharedData>();
const today = new Intl.DateTimeFormat('fr-SN', { weekday: 'long', day: 'numeric', month: 'long', timeZone: 'Africa/Dakar' }).format(new Date());
</script>

<template>
    <header
        class="sticky top-0 z-20 flex h-[74px] shrink-0 items-center justify-between border-b border-border/60 bg-background/55 px-4 backdrop-blur-2xl md:px-6"
    >
        <div class="flex min-w-0 items-center gap-3">
            <SidebarTrigger class="-ml-1 rounded-xl border border-border/70 bg-card/70 shadow-sm" />
            <div class="min-w-0">
                <template v-if="breadcrumbs.length">
                    <Breadcrumb>
                        <BreadcrumbList class="flex-nowrap">
                            <template v-for="(item, index) in breadcrumbs" :key="item.href">
                                <BreadcrumbItem class="min-w-0">
                                    <BreadcrumbPage v-if="index === breadcrumbs.length - 1" class="truncate font-semibold text-foreground">{{
                                        item.title
                                    }}</BreadcrumbPage>
                                    <BreadcrumbLink v-else :href="item.href" class="truncate">{{ item.title }}</BreadcrumbLink>
                                </BreadcrumbItem>
                                <BreadcrumbSeparator v-if="index !== breadcrumbs.length - 1" />
                            </template>
                        </BreadcrumbList>
                    </Breadcrumb>
                </template>
                <p v-else class="font-semibold">DISC Digital</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <GlobalSearch />
            <div
                class="hidden items-center gap-2 rounded-xl border border-border/60 bg-card/55 px-3 py-2 text-xs capitalize text-muted-foreground shadow-sm sm:flex"
            >
                <CalendarDays class="size-3.5 text-amber-600" />{{ today }}
            </div>
            <Link
                href="/notifications"
                class="relative grid size-10 place-items-center rounded-xl border border-border/60 bg-card/70 text-muted-foreground shadow-sm transition duration-300 hover:-translate-y-0.5 hover:text-foreground"
            >
                <Bell class="size-[18px]" />
                <span
                    v-if="page.props.notifications.unread_count"
                    class="absolute -right-1 -top-1 grid min-w-5 place-items-center rounded-full bg-red-500 px-1 text-[10px] font-bold leading-5 text-white ring-2 ring-background"
                >
                    {{ page.props.notifications.unread_count > 99 ? '99+' : page.props.notifications.unread_count }}
                </span>
            </Link>
        </div>
    </header>
</template>
