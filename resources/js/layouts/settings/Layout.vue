<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profil',
        href: '/settings/profile',
    },
    {
        title: 'Mot de passe',
        href: '/settings/password',
    },
    {
        title: 'Apparence',
        href: '/settings/appearance',
    },
];

const currentPath = window.location.pathname;
</script>

<template>
    <div class="px-4 py-6 md:px-6 lg:px-8">
        <Heading title="Mon espace" description="Gérez votre profil, votre sécurité et vos préférences" />

        <div class="flex flex-col space-y-8 md:space-y-0 lg:flex-row lg:space-x-12 lg:space-y-0">
            <aside class="w-full max-w-xl lg:w-52">
                <nav class="disc-glass flex flex-col space-x-0 space-y-1 rounded-2xl p-2">
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="item.href"
                        variant="ghost"
                        :class="['w-full justify-start', { 'bg-amber-500/10 text-amber-700 dark:text-amber-300': currentPath === item.href }]"
                        as-child
                    >
                        <Link :href="item.href">
                            {{ item.title }}
                        </Link>
                    </Button>
                </nav>
            </aside>

            <Separator class="my-6 md:hidden" />

            <div class="flex-1 md:max-w-2xl">
                <section class="disc-glass max-w-xl space-y-12 rounded-[24px] p-6 md:p-8">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
