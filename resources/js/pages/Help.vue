<script setup lang="ts">
import UserGuide from '@/components/help/UserGuide.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, LogIn } from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage<SharedData>();
const authenticated = computed(() => Boolean(page.props.auth.user));
const breadcrumbs = [{ title: 'Aide & guide', href: '/aide' }];
</script>

<template>
    <Head title="Aide & guide utilisateur" />

    <AppLayout v-if="authenticated" :breadcrumbs="breadcrumbs">
        <UserGuide />
    </AppLayout>

    <div v-else class="disc-welcome-backdrop min-h-svh">
        <div class="absolute inset-x-0 top-0 z-50 grid h-1 grid-cols-3">
            <span class="bg-[#00853f]" /><span class="bg-[#fdef42]" /><span class="bg-[#e31b23]" />
        </div>
        <header class="sticky top-0 z-20 border-b border-border/60 bg-background/75 backdrop-blur-2xl">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8">
                <Link href="/" class="flex min-w-0 items-center gap-3">
                    <img
                        src="/images/logo-tresor-public.png"
                        alt="Trésor public du Sénégal"
                        class="size-11 shrink-0 rounded-full bg-white object-contain shadow-sm"
                    />
                    <span class="min-w-0"
                        ><strong class="block truncate text-sm">Trésor Public</strong
                        ><small class="block truncate text-[10px] font-semibold uppercase tracking-[0.16em] text-amber-700">DISC Digital</small></span
                    >
                </Link>
                <div class="flex items-center gap-2">
                    <Button variant="ghost" class="hidden sm:inline-flex" as-child
                        ><Link href="/"><ArrowLeft class="mr-2 size-4" /> Accueil</Link></Button
                    >
                    <Button as-child
                        ><Link href="/login"><LogIn class="mr-2 size-4" /> Se connecter</Link></Button
                    >
                </div>
            </div>
        </header>
        <UserGuide />
    </div>
</template>
