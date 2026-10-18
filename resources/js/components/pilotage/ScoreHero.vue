<script setup lang="ts">
import StandingBadge from '@/components/pilotage/StandingBadge.vue';
import { outcomeColors, percent } from '@/lib/pilotage';
import type { Period, Score, Thresholds } from '@/types/pilotage';
import { ArrowDownRight, ArrowUpRight, Minus } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    score: Score;
    title: string;
    description: string;
    period: Period;
    thresholds: Thresholds;
    comparison?: { label: string; rate: number | null } | null;
}>();

const segments = computed(() =>
    (
        [
            { key: 'on_time', label: 'Dans le délai', count: props.score.on_time },
            { key: 'late', label: 'Traités en retard', count: props.score.late },
            { key: 'overdue', label: 'Échus, non traités', count: props.score.overdue },
        ] as const
    ).map((segment) => ({
        ...segment,
        color: outcomeColors[segment.key],
        width: props.score.measured ? (segment.count / props.score.measured) * 100 : 0,
    })),
);
</script>

<template>
    <section class="rounded-2xl border bg-card p-5 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-sm font-medium">{{ title }}</p>
                <p class="text-xs text-muted-foreground">{{ description }}</p>
            </div>
            <StandingBadge :standing="score.standing" />
        </div>

        <div class="mt-4 flex flex-wrap items-end gap-x-6 gap-y-2">
            <p class="text-5xl font-semibold tabular-nums tracking-tight md:text-6xl">
                {{ score.rate ?? '—' }}<span v-if="score.rate !== null" class="text-2xl text-muted-foreground"> %</span>
            </p>
            <div class="pb-1.5 text-sm">
                <p>
                    <strong>{{ score.on_time }}</strong> sur <strong>{{ score.measured }}</strong> courriers échus au {{ period.label }}
                </p>
                <p class="flex items-center gap-1 text-muted-foreground">
                    <template v-if="score.delta !== null">
                        <ArrowUpRight v-if="score.delta > 0" class="size-4 text-emerald-700 dark:text-emerald-400" />
                        <ArrowDownRight v-else-if="score.delta < 0" class="size-4 text-red-600 dark:text-red-400" />
                        <Minus v-else class="size-4" />
                        <span :class="score.significant_change ? 'font-semibold text-foreground' : ''"
                            >{{ score.delta > 0 ? '+' : '' }}{{ score.delta }} pts</span
                        >
                        vs trimestre précédent ({{ percent(score.previous_rate) }})
                    </template>
                    <template v-else>Pas de comparaison possible avec le trimestre précédent</template>
                </p>
                <p v-if="comparison" class="text-muted-foreground">{{ comparison.label }} : {{ percent(comparison.rate) }}</p>
            </div>
        </div>

        <!-- Composition des courriers échus, avec l'objectif en repère -->
        <div class="relative mt-5">
            <div
                class="flex h-3 gap-0.5 overflow-hidden rounded-full bg-muted"
                role="img"
                :aria-label="`${score.on_time} dans le délai, ${score.late} en retard, ${score.overdue} non traités`"
            >
                <div
                    v-for="segment in segments.filter((s) => s.count > 0)"
                    :key="segment.key"
                    class="h-full transition-all duration-700 first:rounded-l-full last:rounded-r-full"
                    :class="segment.color"
                    :style="{ width: `${segment.width}%` }"
                    :title="`${segment.label} : ${segment.count}`"
                />
            </div>
            <div
                class="absolute -top-1.5 h-6 w-0.5 rounded-full bg-foreground/70"
                :style="{ left: `${thresholds.target}%` }"
                :title="`Objectif ${thresholds.target} %`"
            />
        </div>
        <ul class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-xs text-muted-foreground">
            <li v-for="segment in segments" :key="segment.key" class="flex items-center gap-1.5">
                <span class="size-2.5 rounded-sm" :class="segment.color" />{{ segment.label }} :
                <strong class="text-foreground">{{ segment.count }}</strong>
            </li>
            <li class="flex items-center gap-1.5"><span class="h-3 w-0.5 rounded-full bg-foreground/70" />Objectif {{ thresholds.target }} %</li>
            <li v-if="score.not_due">
                Pas encore échus : <strong class="text-foreground">{{ score.not_due }}</strong>
            </li>
        </ul>
    </section>
</template>
