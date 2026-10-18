<script setup lang="ts">
import ActivityTiles from '@/components/pilotage/ActivityTiles.vue';
import HighlightList from '@/components/pilotage/HighlightList.vue';
import LateTable from '@/components/pilotage/LateTable.vue';
import PeriodSwitcher from '@/components/pilotage/PeriodSwitcher.vue';
import QuarterStrip from '@/components/pilotage/QuarterStrip.vue';
import RateCell from '@/components/pilotage/RateCell.vue';
import ScoreHero from '@/components/pilotage/ScoreHero.vue';
import StandingBadge from '@/components/pilotage/StandingBadge.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { days, percent, withPeriod } from '@/lib/pilotage';
import type { AgentRow, DivisionRow, Period, PeriodOption, Scorecard, Thresholds } from '@/types/pilotage';
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight, Info } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    period: Period;
    periods: PeriodOption[];
    thresholds: Thresholds;
    scorecard: Scorecard & { divisions: DivisionRow[]; agents: AgentRow[] | null };
}>();

const headline = computed(() => props.scorecard.scores[0]);
const stages = computed(() => props.scorecard.scores.slice(1));
const breadcrumbs = [{ title: 'Bilan trimestriel', href: '/pilotage' }];
</script>

<template>
    <Head :title="`Bilan ${period.label} — Direction`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-5 p-4 md:p-6">
            <div class="flex flex-col justify-between gap-3 lg:flex-row lg:items-end">
                <div>
                    <p class="disc-eyebrow">Bilan trimestriel · Situation globale</p>
                    <h1 class="text-2xl font-semibold">Direction — {{ period.label }}</h1>
                    <p class="text-sm text-muted-foreground">
                        Du {{ period.months }} {{ period.year }}. Chaque courrier compte dans le trimestre où tombe son échéance.
                    </p>
                </div>
                <PeriodSwitcher :period="period" :periods="periods" href="/pilotage" />
            </div>

            <div class="grid gap-4 xl:grid-cols-[1.4fr_1fr]">
                <ScoreHero
                    :score="headline"
                    :period="period"
                    :thresholds="thresholds"
                    title="Courriers traités dans les délais"
                    description="Signés au plus tard à l'échéance totale (délai agent + délai Directeur, depuis l'arrivée)."
                />
                <HighlightList :highlights="scorecard.highlights" />
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div v-for="stage in stages" :key="stage.key" class="flex items-center justify-between gap-3 rounded-2xl border bg-card p-4">
                    <div>
                        <p class="text-sm font-medium">{{ stage.label }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ stage.key === 'drafting' ? 'Étape des agents' : 'Étape du Directeur' }} · {{ stage.measured }} échus
                        </p>
                        <StandingBadge class="mt-2" :standing="stage.standing" compact />
                    </div>
                    <RateCell :score="stage" />
                </div>
            </div>

            <ActivityTiles :activity="scorecard.activity" :period="period" mode="direction" />

            <QuarterStrip
                :quarters="scorecard.quarters"
                :current="period.key"
                :year="period.year"
                href="/pilotage"
                :thresholds="thresholds"
                label="Part des courriers signés dans le délai total"
            />

            <!-- Situation par division -->
            <section class="overflow-hidden rounded-2xl border bg-card">
                <div class="p-5 pb-3">
                    <h2 class="font-semibold">Situation par division</h2>
                    <p class="text-sm text-muted-foreground">
                        Taux de rédaction dans le délai agent : c'est l'étape que la division maîtrise. Cliquez pour ouvrir le bilan de la division.
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-left text-sm">
                        <thead class="border-y bg-muted/50 text-xs uppercase text-muted-foreground">
                            <tr>
                                <th class="px-4 py-2.5">Division</th>
                                <th class="px-4 py-2.5 text-right">Imputés</th>
                                <th class="px-4 py-2.5 text-right">En cours</th>
                                <th class="px-4 py-2.5 text-right">Rédaction dans le délai</th>
                                <th class="px-4 py-2.5 text-right">Signés dans le délai</th>
                                <th class="px-4 py-2.5"><span class="sr-only">Ouvrir</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="row in scorecard.divisions" :key="row.id" class="hover:bg-accent/40">
                                <td class="px-4 py-2.5">
                                    <Link :href="withPeriod(`/pilotage/divisions/${row.id}`, period.key)" class="font-medium hover:underline">{{
                                        row.code
                                    }}</Link>
                                    <p class="text-xs text-muted-foreground">{{ row.name }}</p>
                                </td>
                                <td class="px-4 py-2.5 text-right tabular-nums">{{ row.received }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums">{{ row.backlog }}</td>
                                <td class="px-4 py-2.5"><RateCell :score="row.drafting" /></td>
                                <td class="px-4 py-2.5 text-right tabular-nums">
                                    {{ percent(row.global_rate) }}
                                    <span class="block text-[11px] text-muted-foreground">{{ row.global_measured }} échus</span>
                                </td>
                                <td class="px-2 py-2.5 text-right">
                                    <Link
                                        :href="withPeriod(`/pilotage/divisions/${row.id}`, period.key)"
                                        :aria-label="`Bilan de la ${row.code}`"
                                        class="inline-grid size-8 place-items-center rounded-md hover:bg-accent"
                                        ><ChevronRight class="size-4"
                                    /></Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Situation par individu -->
            <section v-if="scorecard.agents" class="overflow-hidden rounded-2xl border bg-card">
                <div class="p-5 pb-3">
                    <h2 class="font-semibold">Situation par agent</h2>
                    <p class="flex items-start gap-1.5 text-sm text-muted-foreground">
                        <Info class="mt-0.5 size-4 shrink-0" /> Situations à examiner en premier. Un taux calculé sur moins de
                        {{ thresholds.min_sample }} courriers échus est indicatif (point gris).
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-left text-sm">
                        <thead class="border-y bg-muted/50 text-xs uppercase text-muted-foreground">
                            <tr>
                                <th class="px-4 py-2.5">Agent</th>
                                <th class="px-4 py-2.5 text-right">Attribués</th>
                                <th class="px-4 py-2.5 text-right">Rendus</th>
                                <th class="px-4 py-2.5 text-right">En main</th>
                                <th class="px-4 py-2.5 text-right">Échus non rendus</th>
                                <th class="px-4 py-2.5 text-right">Rédaction (médiane)</th>
                                <th class="px-4 py-2.5 text-right">Dans le délai</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="row in scorecard.agents" :key="row.id" class="hover:bg-accent/40">
                                <td class="px-4 py-2.5">
                                    <Link
                                        v-if="row.can_open"
                                        :href="withPeriod(`/pilotage/agents/${row.id}`, period.key)"
                                        class="font-medium hover:underline"
                                        >{{ row.name }}</Link
                                    >
                                    <span v-else class="font-medium">{{ row.name }}</span>
                                    <p class="text-xs text-muted-foreground">
                                        {{ row.division ?? 'Sans division' }}<template v-if="!row.is_active"> · inactif</template>
                                    </p>
                                </td>
                                <td class="px-4 py-2.5 text-right tabular-nums">{{ row.received }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums">{{ row.produced }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums">{{ row.in_hand }}</td>
                                <td
                                    class="px-4 py-2.5 text-right tabular-nums"
                                    :class="row.drafting.overdue ? 'font-semibold text-red-600 dark:text-red-400' : ''"
                                >
                                    {{ row.drafting.overdue }}
                                </td>
                                <td class="px-4 py-2.5 text-right">{{ days(row.median_drafting_days) }}</td>
                                <td class="px-4 py-2.5"><RateCell :score="row.drafting" /></td>
                            </tr>
                            <tr v-if="!scorecard.agents.length">
                                <td colspan="7" class="px-4 py-10 text-center text-muted-foreground">Aucun agent.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <LateTable
                :late="scorecard.late"
                title="Courriers échus non signés à temps"
                description="Courriers dont l'échéance totale tombe dans le trimestre et qui n'ont pas été signés à temps. Les non traités d'abord."
                show-division
            />
        </div>
    </AppLayout>
</template>
