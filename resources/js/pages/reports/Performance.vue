<script setup lang="ts">
import ReportTabs from '@/components/disc/ReportTabs.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { CourrierType, Division } from '@/types/disc';
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle, ArrowUpRight, Hourglass, Info, RotateCcw, SlidersHorizontal, Timer, TrendingUp } from 'lucide-vue-next';
import { computed, reactive } from 'vue';

interface Stage {
    key: string;
    label: string;
    actor: string;
    count: number;
    median: number | null;
    p90: number | null;
    average: number | null;
    target: number | null;
    is_longest: boolean;
}

interface BacklogStage {
    status: string;
    label: string;
    actor: string;
    count: number;
    median_age: number | null;
    max_age: number | null;
    over_30: number;
    buckets: { label: string; count: number }[];
}

interface OldestCourrier {
    id: number;
    registration_number: string;
    subject: string;
    stage: string;
    division: string | null;
    agent: string | null;
    waiting_since: string;
    age_days: number;
}

interface Compliance {
    label: string;
    description: string;
    measured: number;
    on_time: number;
    late: number;
    rate: number | null;
}

interface DivisionRow {
    label: string;
    name: string | null;
    total: number;
    open: number;
    stuck_over_30: number;
    signed: number;
    median_total_days: number | null;
    median_processing_days: number | null;
    on_time_rate: number | null;
    correction_rate: number | null;
}

interface AgentRow {
    id: number;
    name: string;
    division: string | null;
    in_hand: number;
    in_hand_over_delay: number;
    submitted: number;
    median_processing_days: number | null;
    on_time_rate: number | null;
}

interface TrendRow {
    month: string;
    signed: number;
    median_total_days: number | null;
    on_time_rate: number | null;
}

const props = defineProps<{
    filters: { date_from?: string; date_to?: string; division?: number; type?: number };
    performance: {
        summary: {
            with_response: number;
            signed: number;
            open: number;
            median_total_days: number | null;
            on_time_rate: number | null;
            stuck_over_30: number;
            bottleneck: string | null;
            correction_rate: number | null;
        };
        stages: Stage[];
        backlog: { stages: BacklogStage[]; oldest: OldestCourrier[] };
        compliance: Compliance[];
        corrections: {
            reviewed: number;
            with_correction: number;
            total_corrections: number;
            rate: number | null;
            average: number | null;
            max: number | null;
        };
        divisions: DivisionRow[];
        agents: AgentRow[] | null;
        trend: TrendRow[];
    };
    divisions: Division[];
    types: CourrierType[];
}>();

const form = reactive({
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    division: props.filters.division?.toString() ?? '',
    type: props.filters.type?.toString() ?? '',
});
const applyFilters = () => router.get('/reports/performance', form, { preserveState: true, replace: true });
const resetFilters = () => router.get('/reports/performance', {}, { replace: true });

const summary = computed(() => props.performance.summary);
const days = (value: number | null) => (value === null ? '—' : `${value.toString().replace('.', ',')} j`);
const percent = (value: number | null) => (value === null ? '—' : `${value} %`);

const stageScale = computed(() => Math.max(1, ...props.performance.stages.map((stage) => stage.p90 ?? stage.median ?? 0)));
const backlogScale = computed(() => Math.max(1, ...props.performance.backlog.stages.map((stage) => stage.count)));
const trendScale = computed(() => Math.max(1, ...props.performance.trend.map((row) => row.median_total_days ?? 0)));
const widthOf = (value: number | null, scale: number) => `${value === null ? 0 : Math.max(value > 0 ? 2 : 0, (value / scale) * 100)}%`;

const rateTone = (rate: number | null) =>
    rate === null
        ? 'text-muted-foreground'
        : rate >= 80
          ? 'text-emerald-700 dark:text-emerald-400'
          : rate >= 50
            ? 'text-amber-700 dark:text-amber-300'
            : 'text-red-600 dark:text-red-400';

const formatMonth = (month: string) => new Intl.DateTimeFormat('fr-FR', { month: 'short', year: '2-digit' }).format(new Date(`${month}-01T00:00:00`));
const formatDate = (value: string) => new Intl.DateTimeFormat('fr-SN', { dateStyle: 'medium' }).format(new Date(`${value}T00:00:00`));

const breadcrumbs = [
    { title: 'Rapports', href: '/reports' },
    { title: 'Performance', href: '/reports/performance' },
];
</script>

<template>
    <Head title="Performance du traitement" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-5 p-4 md:p-6">
            <div class="flex flex-col justify-between gap-3 lg:flex-row lg:items-end">
                <div>
                    <h1 class="text-2xl font-semibold">Performance du traitement</h1>
                    <p class="text-sm text-muted-foreground">
                        Où passe le temps et où les courriers attendent. Courriers nécessitant une réponse, durées en jours calendaires.
                    </p>
                </div>
                <ReportTabs current="performance" />
            </div>

            <form class="grid gap-3 rounded-xl border bg-card p-4 sm:grid-cols-2 lg:grid-cols-6" @submit.prevent="applyFilters">
                <Input v-model="form.date_from" type="date" aria-label="Arrivés à partir du" title="Arrivés à partir du" />
                <Input v-model="form.date_to" type="date" aria-label="Arrivés jusqu'au" title="Arrivés jusqu'au" />
                <select v-model="form.division" class="h-10 rounded-md border bg-background px-3 text-sm" aria-label="Division">
                    <option value="">Toutes divisions</option>
                    <option v-for="division in divisions" :key="division.id" :value="division.id">{{ division.code }}</option>
                </select>
                <select v-model="form.type" class="h-10 rounded-md border bg-background px-3 text-sm" aria-label="Type">
                    <option value="">Tous les types</option>
                    <option v-for="type in types" :key="type.id" :value="type.id">{{ type.name }}</option>
                </select>
                <Button><SlidersHorizontal class="mr-2 size-4" /> Appliquer</Button>
                <Button type="button" variant="outline" @click="resetFilters"><RotateCcw class="mr-2 size-4" /> Réinitialiser</Button>
            </form>

            <!-- Indicateurs clés -->
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <div class="rounded-xl border bg-card p-4">
                    <p class="flex items-center gap-1.5 text-sm text-muted-foreground"><Timer class="size-4" /> Délai médian de traitement</p>
                    <p class="mt-1 text-3xl font-semibold">{{ days(summary.median_total_days) }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">de l'arrivée à la signature · {{ summary.signed }} signés</p>
                </div>
                <div class="rounded-xl border bg-card p-4">
                    <p class="text-sm text-muted-foreground">Signés dans les délais</p>
                    <p class="mt-1 text-3xl font-semibold" :class="rateTone(summary.on_time_rate)">{{ percent(summary.on_time_rate) }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">délai agent + délai Directeur</p>
                </div>
                <div class="rounded-xl border bg-card p-4">
                    <p class="flex items-center gap-1.5 text-sm text-muted-foreground"><Hourglass class="size-4" /> En cours</p>
                    <p class="mt-1 text-3xl font-semibold">{{ summary.open }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">sur {{ summary.with_response }} courriers à réponse</p>
                </div>
                <div class="rounded-xl border bg-card p-4">
                    <p class="flex items-center gap-1.5 text-sm text-muted-foreground"><AlertTriangle class="size-4" /> Bloqués depuis +30 j</p>
                    <p class="mt-1 text-3xl font-semibold" :class="summary.stuck_over_30 ? 'text-red-600 dark:text-red-400' : ''">
                        {{ summary.stuck_over_30 }}
                    </p>
                    <p class="mt-1 truncate text-xs text-muted-foreground" :title="summary.bottleneck ?? undefined">
                        {{ summary.bottleneck ? `Surtout : ${summary.bottleneck}` : 'Aucun blocage' }}
                    </p>
                </div>
                <div class="rounded-xl border bg-card p-4">
                    <p class="flex items-center gap-1.5 text-sm text-muted-foreground"><RotateCcw class="size-4" /> Renvoyés en correction</p>
                    <p class="mt-1 text-3xl font-semibold">{{ percent(summary.correction_rate) }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">des projets passés en validation</p>
                </div>
            </div>

            <div class="grid gap-4 xl:grid-cols-2">
                <!-- Où passe le temps -->
                <section class="rounded-xl border bg-card p-5">
                    <h2 class="font-semibold">Où passe le temps</h2>
                    <p class="text-sm text-muted-foreground">
                        Durée médiane de chaque étape terminée. Le trait indique la durée pour 9 courriers sur 10.
                    </p>
                    <ol class="mt-5 space-y-4">
                        <li v-for="(stage, index) in performance.stages" :key="stage.key">
                            <div class="flex items-baseline justify-between gap-3 text-sm">
                                <p class="min-w-0">
                                    <span class="text-muted-foreground">{{ index + 1 }}.</span>
                                    <span class="font-medium">{{ stage.label }}</span>
                                    <span
                                        v-if="stage.is_longest"
                                        class="ml-2 inline-flex items-center gap-1 rounded-full bg-amber-500/15 px-2 py-0.5 text-[11px] font-semibold text-amber-800 dark:text-amber-300"
                                        ><AlertTriangle class="size-3" /> Étape la plus longue</span
                                    >
                                    <span class="block text-xs text-muted-foreground">{{ stage.actor }}</span>
                                </p>
                                <p class="shrink-0 text-right">
                                    <strong class="text-base">{{ days(stage.median) }}</strong>
                                    <span class="block text-xs text-muted-foreground">9/10 : {{ days(stage.p90) }} · {{ stage.count }} mesurés</span>
                                </p>
                            </div>
                            <div
                                class="relative mt-2 h-2.5 rounded-full bg-muted"
                                :title="`${stage.label} — médiane ${days(stage.median)}, moyenne ${days(stage.average)}, 9 sur 10 en moins de ${days(stage.p90)}${stage.target ? `, délai prévu ${days(stage.target)}` : ''}`"
                            >
                                <div
                                    class="h-full rounded-full transition-all duration-500"
                                    :class="stage.is_longest ? 'bg-amber-600 dark:bg-amber-400' : 'bg-primary'"
                                    :style="{ width: widthOf(stage.median, stageScale) }"
                                />
                                <div
                                    v-if="stage.p90 !== null"
                                    class="absolute -top-1 h-[18px] w-0.5 rounded-full bg-foreground/45"
                                    :style="{ left: `min(calc(100% - 2px), ${widthOf(stage.p90, stageScale)})` }"
                                />
                            </div>
                            <p v-if="stage.target" class="mt-1 text-xs text-muted-foreground">
                                Délai prévu : {{ days(stage.target) }}
                                <template v-if="stage.median !== null && stage.median > stage.target">
                                    —
                                    <span class="font-medium text-red-600 dark:text-red-400">dépassé de {{ days(stage.median - stage.target) }}</span>
                                </template>
                            </p>
                        </li>
                    </ol>
                </section>

                <!-- Où ça bloque maintenant -->
                <section class="rounded-xl border bg-card p-5">
                    <h2 class="font-semibold">Où ça bloque en ce moment</h2>
                    <p class="text-sm text-muted-foreground">Courriers en cours, par étape en attente, et depuis combien de temps.</p>
                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full min-w-[560px] text-left text-sm">
                            <thead class="text-xs uppercase text-muted-foreground">
                                <tr class="border-b">
                                    <th class="py-2 pr-3 font-medium">Étape</th>
                                    <th class="px-2 py-2 text-right font-medium">En attente</th>
                                    <th
                                        v-for="bucket in performance.backlog.stages[0]?.buckets ?? []"
                                        :key="bucket.label"
                                        class="px-2 py-2 text-right font-medium"
                                    >
                                        {{ bucket.label }}
                                    </th>
                                    <th class="py-2 pl-2 text-right font-medium">Âge médian</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="stage in performance.backlog.stages" :key="stage.status">
                                    <td class="py-2.5 pr-3">
                                        <p class="font-medium">{{ stage.label }}</p>
                                        <p class="text-xs text-muted-foreground">{{ stage.actor }}</p>
                                        <div class="mt-1.5 h-1.5 rounded-full bg-muted" :title="`${stage.count} courriers en attente`">
                                            <div class="h-full rounded-full bg-primary" :style="{ width: widthOf(stage.count, backlogScale) }" />
                                        </div>
                                    </td>
                                    <td class="px-2 py-2.5 text-right text-base font-semibold">{{ stage.count }}</td>
                                    <td
                                        v-for="(bucket, i) in stage.buckets"
                                        :key="bucket.label"
                                        class="px-2 py-2.5 text-right tabular-nums"
                                        :class="[
                                            bucket.count === 0 ? 'text-muted-foreground/60' : '',
                                            i === stage.buckets.length - 1 && bucket.count > 0 ? 'font-semibold text-red-600 dark:text-red-400' : '',
                                        ]"
                                    >
                                        {{ bucket.count }}
                                    </td>
                                    <td class="py-2.5 pl-2 text-right">
                                        {{ days(stage.median_age) }}
                                        <span v-if="stage.max_age !== null" class="block text-xs text-muted-foreground"
                                            >max {{ days(stage.max_age) }}</span
                                        >
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <!-- Les plus anciens -->
            <section class="overflow-hidden rounded-xl border bg-card">
                <div class="p-5 pb-3">
                    <h2 class="font-semibold">Les courriers qui attendent depuis le plus longtemps</h2>
                    <p class="text-sm text-muted-foreground">À traiter en priorité. Ancienneté calculée depuis l'entrée dans l'étape actuelle.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-left text-sm">
                        <thead class="border-y bg-muted/50 text-xs uppercase text-muted-foreground">
                            <tr>
                                <th class="px-4 py-2.5">N° DISC</th>
                                <th class="px-4 py-2.5">Objet</th>
                                <th class="px-4 py-2.5">Étape</th>
                                <th class="px-4 py-2.5">Division / Agent</th>
                                <th class="px-4 py-2.5 text-right">Attend depuis</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="row in performance.backlog.oldest" :key="row.id">
                                <td class="px-4 py-2.5 font-medium">
                                    <Link
                                        class="inline-flex items-center gap-1 text-amber-700 hover:underline dark:text-amber-300"
                                        :href="`/courriers/${row.id}`"
                                        >{{ row.registration_number }} <ArrowUpRight class="size-3.5"
                                    /></Link>
                                </td>
                                <td class="max-w-sm px-4 py-2.5">
                                    <p class="line-clamp-1" :title="row.subject">{{ row.subject }}</p>
                                </td>
                                <td class="px-4 py-2.5">{{ row.stage }}</td>
                                <td class="px-4 py-2.5">
                                    {{ row.division ?? '—' }}
                                    <span class="block text-xs text-muted-foreground">{{ row.agent ?? 'Non affecté' }}</span>
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <strong :class="row.age_days > 30 ? 'text-red-600 dark:text-red-400' : ''">{{ days(row.age_days) }}</strong>
                                    <span class="block text-xs text-muted-foreground">{{ formatDate(row.waiting_since) }}</span>
                                </td>
                            </tr>
                            <tr v-if="!performance.backlog.oldest.length">
                                <td colspan="5" class="px-4 py-10 text-center text-muted-foreground">Aucun courrier en attente pour ces filtres.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="grid gap-4 lg:grid-cols-3">
                <!-- Respect des délais -->
                <section class="rounded-xl border bg-card p-5 lg:col-span-2">
                    <h2 class="font-semibold">Respect des délais</h2>
                    <p class="text-sm text-muted-foreground">Part des étapes terminées dans le délai fixé par le type de courrier.</p>
                    <div class="mt-4 grid gap-4 sm:grid-cols-3">
                        <div v-for="item in performance.compliance" :key="item.label">
                            <p class="text-sm font-medium">{{ item.label }}</p>
                            <p class="text-3xl font-semibold" :class="rateTone(item.rate)">{{ percent(item.rate) }}</p>
                            <div class="mt-2 h-2 rounded-full bg-muted" :title="`${item.on_time} dans les délais, ${item.late} en retard`">
                                <div class="h-full rounded-full bg-primary" :style="{ width: `${item.rate ?? 0}%` }" />
                            </div>
                            <p class="mt-1.5 text-xs text-muted-foreground">{{ item.description }}</p>
                            <p class="text-xs text-muted-foreground">{{ item.on_time }} à l'heure · {{ item.late }} en retard</p>
                        </div>
                    </div>
                </section>

                <!-- Corrections -->
                <section class="rounded-xl border bg-card p-5">
                    <h2 class="font-semibold">Allers-retours de correction</h2>
                    <p class="text-sm text-muted-foreground">Projets renvoyés à l'agent par le Chef de Division.</p>
                    <dl class="mt-4 grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt class="text-muted-foreground">Taux de renvoi</dt>
                            <dd class="text-2xl font-semibold">{{ percent(performance.corrections.rate) }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Renvois par projet</dt>
                            <dd class="text-2xl font-semibold">{{ performance.corrections.average?.toString().replace('.', ',') ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Projets relus</dt>
                            <dd class="font-semibold">{{ performance.corrections.reviewed }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Max sur un projet</dt>
                            <dd class="font-semibold">{{ performance.corrections.max ?? '—' }}</dd>
                        </div>
                    </dl>
                </section>
            </div>

            <!-- Par division -->
            <section class="overflow-hidden rounded-xl border bg-card">
                <div class="p-5 pb-3">
                    <h2 class="font-semibold">Par division</h2>
                    <p class="text-sm text-muted-foreground">Triées par nombre de courriers bloqués depuis plus de 30 jours.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[820px] text-left text-sm">
                        <thead class="border-y bg-muted/50 text-xs uppercase text-muted-foreground">
                            <tr>
                                <th class="px-4 py-2.5">Division</th>
                                <th class="px-4 py-2.5 text-right">Courriers</th>
                                <th class="px-4 py-2.5 text-right">En cours</th>
                                <th class="px-4 py-2.5 text-right">Bloqués +30 j</th>
                                <th class="px-4 py-2.5 text-right">Rédaction (médiane)</th>
                                <th class="px-4 py-2.5 text-right">Total (médiane)</th>
                                <th class="px-4 py-2.5 text-right">Dans les délais</th>
                                <th class="px-4 py-2.5 text-right">Renvois</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="row in performance.divisions" :key="row.label">
                                <td class="px-4 py-2.5">
                                    <p class="font-medium">{{ row.label }}</p>
                                    <p v-if="row.name" class="text-xs text-muted-foreground">{{ row.name }}</p>
                                </td>
                                <td class="px-4 py-2.5 text-right tabular-nums">{{ row.total }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums">{{ row.open }}</td>
                                <td
                                    class="px-4 py-2.5 text-right tabular-nums"
                                    :class="row.stuck_over_30 ? 'font-semibold text-red-600 dark:text-red-400' : ''"
                                >
                                    {{ row.stuck_over_30 }}
                                </td>
                                <td class="px-4 py-2.5 text-right">{{ days(row.median_processing_days) }}</td>
                                <td class="px-4 py-2.5 text-right">{{ days(row.median_total_days) }}</td>
                                <td class="px-4 py-2.5 text-right font-medium" :class="rateTone(row.on_time_rate)">
                                    {{ percent(row.on_time_rate) }}
                                </td>
                                <td class="px-4 py-2.5 text-right">{{ percent(row.correction_rate) }}</td>
                            </tr>
                            <tr v-if="!performance.divisions.length">
                                <td colspan="8" class="px-4 py-10 text-center text-muted-foreground">Aucune donnée pour ces filtres.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Par agent (chefs de division, Directeur, admin) -->
            <section v-if="performance.agents" class="overflow-hidden rounded-xl border bg-card">
                <div class="p-5 pb-3">
                    <h2 class="font-semibold">Charge et rythme par agent</h2>
                    <p class="flex items-start gap-1.5 text-sm text-muted-foreground">
                        <Info class="mt-0.5 size-4 shrink-0" /> Outil de pilotage pour répartir la charge, visible uniquement par les chefs de
                        division, le Directeur et les administrateurs.
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-left text-sm">
                        <thead class="border-y bg-muted/50 text-xs uppercase text-muted-foreground">
                            <tr>
                                <th class="px-4 py-2.5">Agent</th>
                                <th class="px-4 py-2.5 text-right">Dossiers en main</th>
                                <th class="px-4 py-2.5 text-right">Dont délai dépassé</th>
                                <th class="px-4 py-2.5 text-right">Projets rendus</th>
                                <th class="px-4 py-2.5 text-right">Rédaction (médiane)</th>
                                <th class="px-4 py-2.5 text-right">Dans le délai agent</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="row in performance.agents" :key="row.id">
                                <td class="px-4 py-2.5">
                                    <p class="font-medium">{{ row.name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ row.division ?? '—' }}</p>
                                </td>
                                <td class="px-4 py-2.5 text-right tabular-nums">{{ row.in_hand }}</td>
                                <td
                                    class="px-4 py-2.5 text-right tabular-nums"
                                    :class="row.in_hand_over_delay ? 'font-semibold text-red-600 dark:text-red-400' : ''"
                                >
                                    {{ row.in_hand_over_delay }}
                                </td>
                                <td class="px-4 py-2.5 text-right tabular-nums">{{ row.submitted }}</td>
                                <td class="px-4 py-2.5 text-right">{{ days(row.median_processing_days) }}</td>
                                <td class="px-4 py-2.5 text-right font-medium" :class="rateTone(row.on_time_rate)">
                                    {{ percent(row.on_time_rate) }}
                                </td>
                            </tr>
                            <tr v-if="!performance.agents.length">
                                <td colspan="6" class="px-4 py-10 text-center text-muted-foreground">
                                    Aucun courrier attribué à un agent pour ces filtres.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Tendance -->
            <section class="rounded-xl border bg-card p-5">
                <h2 class="flex items-center gap-2 font-semibold"><TrendingUp class="size-4" /> Évolution mois par mois</h2>
                <p class="text-sm text-muted-foreground">
                    Délai médian arrivée → signature, par mois de signature (12 derniers mois de la sélection).
                </p>
                <div v-if="performance.trend.length" class="mt-5 space-y-2.5">
                    <div
                        v-for="row in performance.trend"
                        :key="row.month"
                        class="grid grid-cols-[4.5rem_1fr_auto] items-center gap-3 text-sm"
                        :title="`${formatMonth(row.month)} — ${row.signed} signés, médiane ${days(row.median_total_days)}, ${percent(row.on_time_rate)} dans les délais`"
                    >
                        <span class="capitalize text-muted-foreground">{{ formatMonth(row.month) }}</span>
                        <div class="h-2.5 rounded-full bg-muted">
                            <div class="h-full rounded-full bg-primary" :style="{ width: widthOf(row.median_total_days, trendScale) }" />
                        </div>
                        <span class="w-36 text-right tabular-nums">
                            <strong>{{ days(row.median_total_days) }}</strong>
                            <span class="text-xs text-muted-foreground"> · {{ row.signed }} signés · {{ percent(row.on_time_rate) }}</span>
                        </span>
                    </div>
                </div>
                <p v-else class="mt-4 text-sm text-muted-foreground">Aucun courrier signé pour ces filtres.</p>
            </section>
        </div>
    </AppLayout>
</template>
