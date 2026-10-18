<script setup lang="ts">
import { standingMeta } from '@/lib/pilotage';
import type { QuarterCell, Thresholds } from '@/types/pilotage';
import { Link } from '@inertiajs/vue3';

defineProps<{ quarters: QuarterCell[]; current: string; year: number; href: string; thresholds: Thresholds; label: string }>();
</script>

<template>
    <section class="rounded-2xl border bg-card p-5">
        <h2 class="font-semibold">Année {{ year }}, trimestre par trimestre</h2>
        <p class="text-sm text-muted-foreground">{{ label }}. Cliquez sur un trimestre pour l'afficher.</p>
        <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
            <component
                :is="quarter.is_future ? 'div' : Link"
                v-for="quarter in quarters"
                :key="quarter.key"
                :href="quarter.is_future ? undefined : `${href}?periode=${quarter.key}`"
                preserve-scroll
                class="rounded-xl border p-3 transition-colors"
                :class="[
                    quarter.key === current ? 'border-primary bg-primary/5 ring-1 ring-primary' : '',
                    quarter.is_future ? 'opacity-50' : 'hover:bg-accent/50',
                ]"
                :aria-current="quarter.key === current ? 'page' : undefined"
            >
                <div class="flex items-baseline justify-between">
                    <span class="text-sm font-semibold">{{ quarter.label }}</span>
                    <span v-if="quarter.is_current" class="text-[10px] font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300"
                        >En cours</span
                    >
                </div>
                <p class="text-[11px] text-muted-foreground">{{ quarter.months }}</p>
                <p class="mt-2 text-2xl font-semibold tabular-nums">{{ quarter.rate === null ? '—' : `${quarter.rate} %` }}</p>
                <div class="relative mt-1.5 h-1.5 rounded-full bg-muted">
                    <div class="h-full rounded-full" :class="standingMeta[quarter.standing].dot" :style="{ width: `${quarter.rate ?? 0}%` }" />
                    <div class="absolute -top-1 h-3.5 w-0.5 rounded-full bg-foreground/50" :style="{ left: `${thresholds.target}%` }" />
                </div>
                <p class="mt-1.5 text-[11px] text-muted-foreground">
                    {{
                        quarter.is_future
                            ? 'À venir'
                            : `${quarter.measured} courrier${quarter.measured > 1 ? 's' : ''} échu${quarter.measured > 1 ? 's' : ''}`
                    }}
                </p>
            </component>
        </div>
    </section>
</template>
