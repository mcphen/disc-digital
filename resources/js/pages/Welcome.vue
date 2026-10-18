<script setup lang="ts">
import type { SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, BellRing, Check, CircleHelp, FileCheck2, Gauge, ShieldCheck } from 'lucide-vue-next';

const page = usePage<SharedData>();
</script>

<template>
    <Head title="DISC Digital — Courrier intelligent" />
    <div class="disc-welcome-backdrop min-h-svh">
        <div class="absolute inset-x-0 top-0 grid h-1 grid-cols-3">
            <span class="bg-[#00853f]" /><span class="bg-[#fdef42]" /><span class="bg-[#e31b23]" />
        </div>
        <div class="disc-orb -left-28 top-20 size-96 bg-amber-300/30 blur-3xl dark:bg-amber-600/10" />
        <div class="disc-orb -right-32 top-1/3 size-[420px] bg-emerald-300/15 blur-3xl dark:bg-emerald-600/10" style="animation-delay: -9s" />

        <header class="mx-auto flex max-w-7xl items-center justify-between px-5 py-5 sm:px-8 lg:px-10">
            <Link :href="route('home')" class="flex items-center gap-3">
                <span class="grid size-14 place-items-center rounded-full bg-white p-0.5 shadow-lg shadow-black/15"
                    ><img src="/images/logo-tresor-public.png" alt="Trésor public du Sénégal" class="size-full object-contain"
                /></span>
                <span
                    ><strong class="block tracking-[-0.02em]">Trésor Public</strong
                    ><small class="text-[10px] font-semibold uppercase tracking-[0.16em] text-amber-700 dark:text-amber-300"
                        >DISC Digital</small
                    ></span
                >
            </Link>
            <div class="flex items-center gap-2">
                <Link
                    href="/aide"
                    class="hidden h-11 items-center gap-2 rounded-2xl px-4 text-sm font-semibold text-muted-foreground transition hover:bg-card/70 hover:text-foreground sm:inline-flex"
                    ><CircleHelp class="size-4" /> Aide</Link
                >
                <Link
                    :href="page.props.auth.user ? route('dashboard') : route('login')"
                    class="inline-flex h-11 items-center gap-2 rounded-2xl border border-border/70 bg-card/70 px-4 text-sm font-semibold shadow-sm backdrop-blur-xl transition duration-300 hover:-translate-y-0.5 hover:border-amber-400 sm:px-5"
                >
                    {{ page.props.auth.user ? 'Tableau de bord' : 'Se connecter' }} <ArrowRight class="size-4" />
                </Link>
            </div>
        </header>

        <main class="mx-auto grid max-w-7xl items-center gap-14 px-5 pb-16 pt-12 sm:px-8 lg:grid-cols-[0.9fr_1.1fr] lg:px-10 lg:pb-24 lg:pt-20">
            <section class="disc-page-enter max-w-2xl">
                <div
                    class="mb-7 inline-flex items-center gap-2 rounded-full border border-amber-300/70 bg-amber-50/70 px-3 py-1.5 text-xs font-semibold text-amber-800 backdrop-blur-xl dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300"
                >
                    <span class="size-1.5 rounded-full bg-[#00853f] shadow-[0_0_0_4px_rgba(0,133,63,.12)]" /> Plateforme opérationnelle
                </div>
                <h1 class="text-5xl font-semibold leading-[1.02] tracking-[-0.055em] sm:text-6xl xl:text-7xl">
                    Le courrier,<br /><span class="bg-gradient-to-r from-amber-600 to-yellow-500 bg-clip-text text-transparent"
                        >enfin sous contrôle.</span
                    >
                </h1>
                <p class="mt-7 max-w-xl text-lg leading-8 text-muted-foreground">
                    Centralisez les arrivées, suivez chaque affectation et anticipez les retards dans une interface pensée pour la décision.
                </p>

                <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                    <Link
                        :href="page.props.auth.user ? route('dashboard') : route('login')"
                        class="inline-flex h-12 items-center justify-center gap-3 rounded-2xl bg-primary px-6 text-sm font-semibold text-primary-foreground shadow-xl shadow-amber-500/20 transition duration-300 hover:-translate-y-1"
                    >
                        Accéder à DISC Digital <ArrowRight class="size-4" />
                    </Link>
                    <div class="flex items-center gap-2 px-3 text-sm text-muted-foreground">
                        <ShieldCheck class="size-4 text-emerald-500" /> Accès sécurisé par rôle
                    </div>
                </div>

                <div class="mt-12 grid max-w-lg grid-cols-3 gap-4 border-t border-border/70 pt-7">
                    <div>
                        <strong class="block text-2xl tracking-tight">170</strong><span class="text-xs text-muted-foreground">courriers suivis</span>
                    </div>
                    <div>
                        <strong class="block text-2xl tracking-tight">6</strong><span class="text-xs text-muted-foreground">profils métiers</span>
                    </div>
                    <div>
                        <strong class="block text-2xl tracking-tight">24/7</strong><span class="text-xs text-muted-foreground">alertes actives</span>
                    </div>
                </div>
            </section>

            <section class="disc-page-enter relative [animation-delay:180ms]">
                <div class="disc-glass relative rounded-[32px] p-3 shadow-[0_40px_100px_-40px_rgba(120,85,20,.38)] sm:p-5">
                    <div class="overflow-hidden rounded-[24px] border border-border/70 bg-card/90">
                        <div class="flex items-center justify-between border-b border-border/60 px-5 py-4">
                            <div>
                                <p class="disc-eyebrow">Vue d’ensemble</p>
                                <h2 class="mt-1 font-semibold">Activité du courrier</h2>
                            </div>
                            <div class="flex gap-1.5">
                                <span class="size-2.5 rounded-full bg-red-400" /><span class="size-2.5 rounded-full bg-amber-400" /><span
                                    class="size-2.5 rounded-full bg-emerald-400"
                                />
                            </div>
                        </div>
                        <div class="grid gap-3 p-4 sm:grid-cols-3 sm:p-5">
                            <div class="rounded-2xl bg-amber-50 p-4 dark:bg-amber-950/30">
                                <FileCheck2 class="size-5 text-amber-600" /><strong class="mt-5 block text-3xl">170</strong
                                ><span class="text-xs text-muted-foreground">Enregistrés</span>
                            </div>
                            <div class="rounded-2xl bg-red-50 p-4 dark:bg-red-950/30">
                                <BellRing class="size-5 text-red-500" /><strong class="mt-5 block text-3xl">32</strong
                                ><span class="text-xs text-muted-foreground">À orienter</span>
                            </div>
                            <div class="rounded-2xl bg-emerald-50 p-4 dark:bg-emerald-950/30">
                                <Gauge class="size-5 text-emerald-500" /><strong class="mt-5 block text-3xl">81%</strong
                                ><span class="text-xs text-muted-foreground">Affectés</span>
                            </div>
                        </div>
                        <div class="px-4 pb-5 sm:px-5">
                            <div class="rounded-2xl border border-border/60 bg-background/60 p-4">
                                <div class="mb-4 flex items-center justify-between">
                                    <p class="text-sm font-semibold">Dernières activités</p>
                                    <span class="text-xs text-muted-foreground">Temps réel</span>
                                </div>
                                <div class="space-y-3">
                                    <div
                                        v-for="item in [
                                            'Courrier affecté à la DRI',
                                            'Réponse transmise à la direction',
                                            'Nouvelle pièce jointe ajoutée',
                                        ]"
                                        :key="item"
                                        class="flex items-center gap-3 rounded-xl bg-card p-3 shadow-sm"
                                    >
                                        <span class="grid size-7 place-items-center rounded-full bg-emerald-100 text-emerald-600"
                                            ><Check class="size-3.5" /></span
                                        ><span class="flex-1 truncate text-xs font-medium">{{ item }}</span
                                        ><span class="text-[10px] text-muted-foreground">maintenant</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
</template>
