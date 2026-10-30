<script setup lang="ts">
import AlertBadge from '@/components/disc/AlertBadge.vue';
import StatusBadge from '@/components/disc/StatusBadge.vue';
import QuarterStrip from '@/components/pilotage/QuarterStrip.vue';
import StandingBadge from '@/components/pilotage/StandingBadge.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { SharedData } from '@/types';
import type { Courrier } from '@/types/disc';
import type { QuarterCell, Standing, Thresholds } from '@/types/pilotage';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, BellRing, CalendarRange, Check, Clock3, FilePlus2, Inbox, ShieldCheck, Sparkles, TrendingUp } from 'lucide-vue-next';

defineProps<{
    permissions: {
        create: boolean;
        bulkAssign: boolean;
        reports: boolean;
        review: boolean;
        sign: boolean;
    };
    stats: {
        total: number;
        without_response: number;
        requiring_response: number;
        signed: number;
        on_time: number;
        yellow: number;
        red: number;
        pending_review: number;
        corrections_requested: number;
        pending_signature: number;
        completion_rate: number;
        compliance_rate: number;
    };
    quarter: {
        scope: string;
        stage: string;
        period: string;
        year: number;
        quarters: QuarterCell[];
        current: { rate: number | null; on_time: number; measured: number; standing: Standing };
        previous: { label: string; rate: number | null; on_time: number; measured: number; standing: Standing };
        remaining: number;
        end_date: string;
    } | null;
    thresholds: Thresholds;
    recentCourriers: Courrier[];
}>();

const page = usePage<SharedData>();
const firstName = page.props.auth.user.name.split(' ')[0];
const dateFormat = new Intl.DateTimeFormat('fr-SN', { day: 'numeric', month: 'short', timeZone: 'Africa/Dakar' });
const formatDate = (value: string) => dateFormat.format(new Date(`${value}T00:00:00`));
const breadcrumbs = [{ title: 'Vue d’ensemble', href: '/dashboard' }];
</script>

<template>
    <Head title="Vue d’ensemble" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-5 p-4 md:p-6 lg:p-8">
            <section
                class="relative overflow-hidden rounded-[28px] border-0 bg-[#1c1a17] p-6 text-white shadow-[0_24px_70px_-35px_rgba(85,62,18,.65)] md:p-8"
            >
                <div class="absolute inset-x-0 top-0 grid h-1 grid-cols-3">
                    <span class="bg-[#00853f]" /><span class="bg-[#fdef42]" /><span class="bg-[#e31b23]" />
                </div>
                <div class="absolute -right-16 -top-24 size-72 rounded-full bg-amber-500/20 blur-3xl" />
                <div class="absolute bottom-0 right-1/3 size-40 rounded-full bg-emerald-500/10 blur-3xl" />
                <div class="relative z-10 flex flex-col justify-between gap-8 lg:flex-row lg:items-end">
                    <div>
                        <p class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-amber-300">
                            <Sparkles class="size-3.5" /> Centre de pilotage
                        </p>
                        <h1 class="text-3xl font-semibold tracking-[-0.04em] md:text-4xl">Bonjour {{ firstName }},</h1>
                        <p class="mt-2 max-w-xl text-sm leading-6 text-slate-300">
                            Voici ce qui mérite votre attention aujourd’hui. Les indicateurs se mettent à jour avec chaque action de votre équipe.
                        </p>
                    </div>
                    <Button
                        v-if="permissions.create"
                        size="lg"
                        as-child
                        class="border-0 bg-amber-300 text-stone-950 shadow-xl shadow-black/20 hover:bg-amber-200"
                    >
                        <Link href="/courriers/create"><FilePlus2 class="mr-1 size-4" /> Nouveau courrier</Link>
                    </Button>
                </div>
            </section>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <Link
                    href="/courriers"
                    class="group rounded-[24px] border border-border/70 bg-card/80 p-5 shadow-[0_18px_50px_-38px_rgba(15,23,42,.5)] backdrop-blur-xl transition duration-500 hover:-translate-y-1"
                >
                    <div class="flex items-start justify-between">
                        <span class="grid size-11 place-items-center rounded-2xl bg-amber-100 text-amber-700 dark:bg-amber-950"
                            ><Inbox class="size-5" /></span
                        ><span
                            class="rounded-full bg-amber-50 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-amber-700 dark:bg-amber-950"
                            >Total</span
                        >
                    </div>
                    <p class="mt-6 text-4xl font-semibold tracking-[-0.05em]">{{ stats.total }}</p>
                    <p class="mt-1 text-sm text-muted-foreground">{{ stats.requiring_response }} nécessitent une réponse</p>
                </Link>
                <Link
                    href="/courriers?queue=review"
                    class="group rounded-[24px] border border-border/70 bg-card/80 p-5 shadow-[0_18px_50px_-38px_rgba(15,23,42,.5)] backdrop-blur-xl transition duration-500 hover:-translate-y-1"
                >
                    <div class="flex items-start justify-between">
                        <span class="grid size-11 place-items-center rounded-2xl bg-amber-100 text-amber-700 dark:bg-amber-950"
                            ><ShieldCheck class="size-5" /></span
                        ><span
                            class="rounded-full bg-amber-50 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-amber-700 dark:bg-amber-950"
                            >Division</span
                        >
                    </div>
                    <p class="mt-6 text-4xl font-semibold tracking-[-0.05em]">{{ stats.pending_review }}</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        à valider <span v-if="stats.corrections_requested">· {{ stats.corrections_requested }} à corriger</span>
                    </p>
                </Link>
                <Link
                    href="/courriers?queue=signature"
                    class="group rounded-[24px] border border-border/70 bg-card/80 p-5 shadow-[0_18px_50px_-38px_rgba(15,23,42,.5)] backdrop-blur-xl transition duration-500 hover:-translate-y-1"
                >
                    <div class="flex items-start justify-between">
                        <span class="grid size-11 place-items-center rounded-2xl bg-yellow-100 text-yellow-700 dark:bg-yellow-950"
                            ><Clock3 class="size-5" /></span
                        ><span
                            class="rounded-full bg-yellow-50 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-yellow-700 dark:bg-yellow-950"
                            >Direction</span
                        >
                    </div>
                    <p class="mt-6 text-4xl font-semibold tracking-[-0.05em]">{{ stats.pending_signature }}</p>
                    <p class="mt-1 text-sm text-muted-foreground">en attente de signature</p>
                </Link>
                <Link
                    href="/courriers?queue=priority"
                    class="group rounded-[24px] border border-red-200/70 bg-gradient-to-br from-red-50 to-card p-5 shadow-[0_18px_50px_-38px_rgba(220,38,38,.55)] transition duration-500 hover:-translate-y-1 dark:border-red-900/60 dark:from-red-950/40"
                >
                    <div class="flex items-start justify-between">
                        <span class="grid size-11 place-items-center rounded-2xl bg-red-100 text-red-600 dark:bg-red-950"
                            ><BellRing class="size-5" /></span
                        ><span
                            class="rounded-full bg-red-100 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-red-600 dark:bg-red-950"
                            >Priorité</span
                        >
                    </div>
                    <p class="mt-6 text-4xl font-semibold tracking-[-0.05em]">{{ stats.red + stats.yellow }}</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        <span class="font-medium text-red-600">{{ stats.red }} critiques</span> · {{ stats.yellow }} à surveiller
                    </p>
                </Link>
                <Link
                    href="/courriers?status=signed"
                    class="group rounded-[24px] border border-border/70 bg-card/80 p-5 shadow-[0_18px_50px_-38px_rgba(15,23,42,.5)] backdrop-blur-xl transition duration-500 hover:-translate-y-1"
                >
                    <div class="flex items-start justify-between">
                        <span class="grid size-11 place-items-center rounded-2xl bg-emerald-100 text-emerald-600 dark:bg-emerald-950"
                            ><TrendingUp class="size-5" /></span
                        ><span
                            class="rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-emerald-600 dark:bg-emerald-950"
                            >Qualité</span
                        >
                    </div>
                    <p class="mt-6 text-4xl font-semibold tracking-[-0.05em]">
                        {{ stats.compliance_rate }}<span class="text-xl text-muted-foreground">%</span>
                    </p>
                    <p class="mt-1 text-sm text-muted-foreground">de signatures dans les délais</p>
                </Link>
            </div>

            <Link
                v-if="quarter"
                href="/pilotage"
                class="group grid gap-4 rounded-[24px] border border-border/70 bg-card/80 p-5 shadow-[0_18px_50px_-38px_rgba(15,23,42,.5)] transition duration-500 hover:-translate-y-0.5 md:grid-cols-[auto_1fr_1fr_auto] md:items-center"
            >
                <span class="grid size-11 place-items-center rounded-2xl bg-amber-100 text-amber-700 dark:bg-amber-950"
                    ><CalendarRange class="size-5"
                /></span>
                <div>
                    <p class="disc-eyebrow">Bilan trimestriel · {{ quarter.scope }}</p>
                    <p class="mt-1 text-sm text-muted-foreground">{{ quarter.stage }}</p>
                    <div class="mt-1 flex flex-wrap items-center gap-2">
                        <strong class="text-2xl tabular-nums">{{ quarter.current.rate === null ? '—' : `${quarter.current.rate} %` }}</strong>
                        <span class="text-sm text-muted-foreground"
                            >{{ quarter.period }} en cours · {{ quarter.current.on_time }}/{{ quarter.current.measured }}</span
                        >
                        <StandingBadge :standing="quarter.current.standing" compact />
                    </div>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">Trimestre précédent ({{ quarter.previous.label }})</p>
                    <div class="mt-1 flex flex-wrap items-center gap-2">
                        <strong class="text-2xl tabular-nums">{{ quarter.previous.rate === null ? '—' : `${quarter.previous.rate} %` }}</strong>
                        <span class="text-sm text-muted-foreground">{{ quarter.previous.on_time }}/{{ quarter.previous.measured }}</span>
                        <StandingBadge :standing="quarter.previous.standing" compact />
                    </div>
                </div>
                <span class="inline-flex items-center gap-1 text-sm font-medium text-amber-700 dark:text-amber-300"
                    >Ouvrir le bilan <ArrowRight class="size-4 transition group-hover:translate-x-0.5"
                /></span>
            </Link>

            <QuarterStrip
                v-if="quarter"
                :quarters="quarter.quarters"
                :current="quarter.quarters.find((item) => item.is_current)?.key ?? ''"
                :year="quarter.year"
                href="/pilotage"
                :thresholds="thresholds"
                :label="`Bilan trimestre par trimestre · ${quarter.scope} · ${quarter.stage}`"
            />

            <div>
                <section class="overflow-hidden rounded-[26px] border border-border/70 bg-card/80 shadow-sm backdrop-blur-xl">
                    <div class="flex items-center justify-between border-b border-border/60 px-5 py-5 sm:px-6">
                        <div>
                            <p class="disc-eyebrow">Flux récent</p>
                            <h2 class="mt-1 text-lg font-semibold tracking-tight">Derniers courriers</h2>
                        </div>
                        <Button variant="ghost" size="sm" as-child
                            ><Link href="/courriers">Tout voir <ArrowRight class="ml-1 size-4" /></Link
                        ></Button>
                    </div>
                    <div v-if="recentCourriers.length" class="divide-y divide-border/60">
                        <Link
                            v-for="courrier in recentCourriers"
                            :key="courrier.id"
                            :href="`/courriers/${courrier.id}`"
                            class="group flex items-center gap-3 px-5 py-3.5 transition duration-300 hover:bg-accent/50 sm:px-6"
                        >
                            <span
                                class="grid size-10 shrink-0 place-items-center rounded-2xl bg-muted text-muted-foreground transition duration-300 group-hover:bg-amber-100 group-hover:text-amber-700 dark:group-hover:bg-amber-950"
                                ><Check class="size-4"
                            /></span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold">{{ courrier.subject }}</p>
                                <p class="mt-0.5 truncate text-xs text-muted-foreground">
                                    {{ courrier.registration_number }} · {{ courrier.origin_service }} · {{ formatDate(courrier.arrival_date) }}
                                </p>
                            </div>
                            <StatusBadge class="hidden lg:inline-flex" :status="courrier.status" :label="courrier.status_label" /><AlertBadge
                                :level="courrier.metrics.current_alert"
                            />
                        </Link>
                    </div>
                    <div v-else class="p-10 text-center text-sm text-muted-foreground">Aucun courrier enregistré pour le moment.</div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
