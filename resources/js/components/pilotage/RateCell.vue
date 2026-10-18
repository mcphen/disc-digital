<script setup lang="ts">
import { standingMeta } from '@/lib/pilotage';
import type { Score } from '@/types/pilotage';

defineProps<{ score: Score }>();
</script>

<template>
    <div class="flex items-center justify-end gap-2">
        <span class="size-2 shrink-0 rounded-full" :class="standingMeta[score.standing].dot" :title="standingMeta[score.standing].label" />
        <div class="text-right">
            <p class="font-semibold tabular-nums" :class="score.standing === 'limited' ? 'text-muted-foreground' : ''">
                {{ score.rate === null ? '—' : `${score.rate} %` }}
            </p>
            <p class="text-[11px] tabular-nums text-muted-foreground">
                {{ score.on_time }}/{{ score.measured }}
                <template v-if="score.delta !== null">
                    ·
                    <span
                        :class="
                            score.significant_change
                                ? score.delta > 0
                                    ? 'font-semibold text-emerald-700 dark:text-emerald-400'
                                    : 'font-semibold text-red-600 dark:text-red-400'
                                : ''
                        "
                        >{{ score.delta > 0 ? '+' : '' }}{{ score.delta }} pts</span
                    >
                </template>
            </p>
        </div>
    </div>
</template>
