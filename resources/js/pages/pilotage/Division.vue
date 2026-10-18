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
import { days, withPeriod } from '@/lib/pilotage';
import type { Division } from '@/types/disc';
import type { AgentRow, Period, PeriodOption, Scorecard, Thresholds } from '@/types/pilotage';
import { Head, Link } from '@inertiajs/vue3';
import { Info } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    period: Period;
    periods: PeriodOption[];
    thresholds: Thresholds;
    division: Division;
    canViewDirection: boolean;
    scorecard: Scorecard & { direction_rate: number | null; agents: AgentRow[] | null };
}>();

const headline = computed(() => props.scorecard.scores[0]);
const global = computed(() => props.scorecard.scores[1]);
const href = computed(() => `/pilotage/divisions/${props.division.id}`);
const breadcrumbs = computed(() => [
    ...(props.canViewDirection ? [{ title: 'Bilan trimestriel', href: withPeriod('/pilotage', props.period.key) }] : []),
    { title: props.division.code, href: href.value },
]);
</script>

<template>
    <Head :title="`Bilan ${period.label} — ${division.code}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-5 p-4 md:p-6">
            <div class="flex flex-col justify-between gap-3 lg:flex-row lg:items-end">
                <div>
                    <p class="disc-eyebrow">Bilan trimestriel · Situation de la division</p>
                    <h1 class="text-2xl font-semibold">{{ division.code }} — {{ period.label }}</h1>
                    <p class="text-sm text-muted-foreground">{{ division.name }} · du {{ period.months }} {{ period.year }}</p>
                </div>
                <PeriodSwitcher :period="period" :periods="periods" :href="href" />
            </div>

            <div class="grid gap-4 xl:grid-cols-[1.4fr_1fr]">
                <ScoreHero
                    :score="headline"
                    :period="period"
                    :thresholds="thresholds"
                    title="Projets de réponse rendus dans le délai"
                    description="Soumis au Chef de Division au plus tard à l'échéance agent (attribution + délai du type de courrier)."
                    :comparison="{ label: 'Moyenne de la Direction', rate: scorecard.direction_rate }"
                />
                <HighlightList :highlights="scorecard.highlights" />
            </div>

            <div class="flex items-center justify-between gap-3 rounded-2xl border bg-card p-4">
                <div>
                    <p class="text-sm font-medium">{{ global.label }}</p>
                    <p class="text-xs text-muted-foreground">Courriers de la division, de l'arrivée à la signature · {{ global.measured }} échus</p>
                    <StandingBadge class="mt-2" :standing="global.standing" compact />
                </div>
                <RateCell :score="global" />
            </div>

            <ActivityTiles :activity="scorecard.activity" :period="period" mode="division" />

            <QuarterStrip
                :quarters="scorecard.quarters"
                :current="period.key"
                :year="period.year"
                :href="href"
                :thresholds="thresholds"
                label="Part des projets rendus dans le délai agent"
            />

            <section v-if="scorecard.agents" class="overflow-hidden rounded-2xl border bg-card">
                <div class="p-5 pb-3">
                    <h2 class="font-semibold">Situation par agent</h2>
                    <p class="flex items-start gap-1.5 text-sm text-muted-foreground">
                        <Info class="mt-0.5 size-4 shrink-0" /> Courriers de la division uniquement. Un taux calculé sur moins de
                        {{ thresholds.min_sample }} courriers échus est indicatif (point gris).
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-left text-sm">
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
                                    <p v-if="!row.is_active" class="text-xs text-muted-foreground">inactif</p>
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
                                <td colspan="7" class="px-4 py-10 text-center text-muted-foreground">Aucun agent dans cette division.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <LateTable
                :late="scorecard.late"
                title="Projets échus non rendus à temps"
                description="Courriers dont l'échéance agent tombe dans le trimestre et dont le projet n'a pas été rendu à temps."
                show-agent
            />
        </div>
    </AppLayout>
</template>
