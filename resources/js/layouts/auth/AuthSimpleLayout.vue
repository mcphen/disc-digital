<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight, Check, ShieldCheck, TimerReset } from 'lucide-vue-next';

defineProps<{ title?: string; description?: string }>();
</script>

<template>
    <div class="disc-auth-backdrop grid min-h-svh lg:grid-cols-[1.08fr_0.92fr]">
        <div class="absolute inset-x-0 top-0 z-50 grid h-1 grid-cols-3">
            <span class="bg-[#00853f]" /><span class="bg-[#fdef42]" /><span class="bg-[#e31b23]" />
        </div>
        <div class="disc-orb -left-24 top-24 size-80 bg-amber-300/30 blur-3xl dark:bg-amber-600/10" />
        <div class="disc-orb -bottom-32 right-1/3 size-96 bg-emerald-300/20 blur-3xl dark:bg-emerald-600/10" style="animation-delay: -10s" />

        <section class="relative hidden min-h-svh flex-col overflow-hidden bg-[#1c1a17] p-10 text-white lg:flex xl:p-14">
            <div
                class="absolute inset-0 bg-[radial-gradient(circle_at_15%_10%,rgba(218,165,39,.24),transparent_36%),radial-gradient(circle_at_85%_80%,rgba(0,133,63,.12),transparent_34%)]"
            />
            <div class="absolute -right-36 top-1/4 size-[420px] rounded-full border border-white/10" />
            <div class="absolute -right-20 top-1/3 size-[280px] rounded-full border border-white/10" />

            <Link :href="route('home')" class="relative z-10 flex items-center gap-3 self-start">
                <span class="grid size-14 place-items-center rounded-full bg-white p-0.5 shadow-xl shadow-black/40"
                    ><img src="/images/logo-tresor-public.png" alt="Trésor public du Sénégal" class="size-full object-contain"
                /></span>
                <span
                    ><strong class="block tracking-[-0.02em]">Trésor Public</strong
                    ><small class="text-xs uppercase tracking-[0.18em] text-amber-300">DISC Digital</small></span
                >
            </Link>

            <div class="relative z-10 my-auto max-w-xl py-16">
                <p class="mb-5 text-xs font-semibold uppercase tracking-[0.22em] text-amber-300">Pilotage nouvelle génération</p>
                <h2 class="text-4xl font-semibold leading-[1.08] tracking-[-0.045em] xl:text-6xl">
                    Chaque courrier.<br /><span class="text-amber-300">Au bon moment.</span>
                </h2>
                <p class="mt-6 max-w-lg text-base leading-7 text-slate-300 xl:text-lg">
                    Une vision claire des affectations, des délais et des décisions. Sans tableurs dispersés, sans perte de contexte.
                </p>

                <div class="mt-10 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-2xl border border-white/10 bg-white/[0.06] p-4 backdrop-blur-xl">
                        <TimerReset class="mb-4 size-5 text-amber-300" />
                        <p class="text-sm font-medium">Délais suivis</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/[0.06] p-4 backdrop-blur-xl">
                        <ShieldCheck class="mb-4 size-5 text-emerald-300" />
                        <p class="text-sm font-medium">Accès maîtrisés</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/[0.06] p-4 backdrop-blur-xl">
                        <Check class="mb-4 size-5 text-yellow-300" />
                        <p class="text-sm font-medium">Traçabilité complète</p>
                    </div>
                </div>
            </div>

            <p class="relative z-10 text-xs text-slate-500">Direction générale de la Comptabilité publique et du Trésor</p>
        </section>

        <main class="relative flex min-h-svh items-center justify-center px-5 py-10 sm:px-8 lg:px-12">
            <div class="disc-page-enter w-full max-w-md">
                <Link :href="route('home')" class="mb-10 flex items-center gap-3 lg:hidden">
                    <span class="grid size-12 place-items-center rounded-full bg-white p-0.5 shadow-lg shadow-amber-500/20"
                        ><img src="/images/logo-tresor-public.png" alt="Trésor public du Sénégal" class="size-full object-contain"
                    /></span>
                    <span
                        ><strong class="block">Trésor Public</strong
                        ><small class="text-[10px] uppercase tracking-[0.16em] text-amber-700 dark:text-amber-300">DISC Digital</small></span
                    >
                </Link>

                <div class="disc-glass rounded-[28px] p-6 sm:p-8">
                    <div class="mb-8">
                        <div class="mb-5 flex size-11 items-center justify-center rounded-2xl bg-amber-500/10 text-amber-700 dark:text-amber-300">
                            <ArrowUpRight class="size-5" />
                        </div>
                        <h1 class="text-3xl font-semibold tracking-[-0.035em]">{{ title }}</h1>
                        <p class="mt-2 text-sm leading-6 text-muted-foreground">{{ description }}</p>
                    </div>
                    <slot />
                </div>

                <p class="mt-6 text-center text-xs text-muted-foreground">Accès sécurisé · Données confidentielles · Session protégée</p>
            </div>
        </main>
    </div>
</template>
