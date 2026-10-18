<script setup lang="ts">
import ActivityTiles from '@/components/pilotage/ActivityTiles.vue';
import HighlightList from '@/components/pilotage/HighlightList.vue';
import LateTable from '@/components/pilotage/LateTable.vue';
import PeriodSwitcher from '@/components/pilotage/PeriodSwitcher.vue';
import QuarterStrip from '@/components/pilotage/QuarterStrip.vue';
import ScoreHero from '@/components/pilotage/ScoreHero.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { withPeriod } from '@/lib/pilotage';
import type { Division } from '@/types/disc';
import type { Period, PeriodOption, Scorecard, Thresholds } from '@/types/pilotage';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    period: Period;
    periods: PeriodOption[];
    thresholds: Thresholds;
    agent: { id: number; name: string; division: Division | null };
    isSelf: boolean;
    canViewDivision: boolean;
    scorecard: Scorecard & { division_rate: number | null };
}>();

const href = computed(() => `/pilotage/agents/${props.agent.id}`);
const breadcrumbs = computed(() => [
    ...(props.canViewDivision && props.agent.division
        ? [{ title: props.agent.division.code, href: withPeriod(`/pilotage/divisions/${props.agent.division.id}`, props.period.key) }]
        : []),
    { title: props.isSelf ? 'Mon bilan trimestriel' : props.agent.name, href: href.value },
]);
</script>

<template>
    <Head :title="`Bilan ${period.label} — ${agent.name}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-5 p-4 md:p-6">
            <div class="flex flex-col justify-between gap-3 lg:flex-row lg:items-end">
                <div>
                    <p class="disc-eyebrow">Bilan trimestriel · {{ isSelf ? 'Mon tableau de bord' : 'Situation individuelle' }}</p>
                    <h1 class="text-2xl font-semibold">{{ agent.name }} — {{ period.label }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ agent.division ? `${agent.division.code} · ${agent.division.name}` : 'Sans division' }} · du {{ period.months }}
                        {{ period.year }}
                    </p>
                </div>
                <PeriodSwitcher :period="period" :periods="periods" :href="href" />
            </div>

            <div class="grid gap-4 xl:grid-cols-[1.4fr_1fr]">
                <ScoreHero
                    :score="scorecard.scores[0]"
                    :period="period"
                    :thresholds="thresholds"
                    title="Projets de réponse rendus dans le délai"
                    description="Un courrier compte dans le trimestre où tombe son échéance : date d'attribution + délai prévu pour son type."
                    :comparison="agent.division ? { label: `Moyenne de la ${agent.division.code}`, rate: scorecard.division_rate } : null"
                />
                <HighlightList :highlights="scorecard.highlights" />
            </div>

            <ActivityTiles :activity="scorecard.activity" :period="period" mode="agent" />

            <QuarterStrip
                :quarters="scorecard.quarters"
                :current="period.key"
                :year="period.year"
                :href="href"
                :thresholds="thresholds"
                label="Part des projets rendus dans le délai"
            />

            <LateTable
                :late="scorecard.late"
                :title="isSelf ? 'Mes courriers en retard sur ce trimestre' : 'Courriers en retard sur ce trimestre'"
                description="Les projets non encore rendus apparaissent en premier : ce sont ceux qui peuvent encore être traités."
            />
        </div>
    </AppLayout>
</template>
