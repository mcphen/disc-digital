<script setup lang="ts">
import type { Highlight } from '@/types/pilotage';
import { AlertOctagon, CheckCircle2, Eye, Info } from 'lucide-vue-next';

defineProps<{ highlights: Highlight[] }>();

const tones = {
    good: { icon: CheckCircle2, class: 'text-emerald-700 dark:text-emerald-400', label: 'Point positif' },
    watch: { icon: Eye, class: 'text-amber-700 dark:text-amber-300', label: 'À surveiller' },
    critical: { icon: AlertOctagon, class: 'text-red-600 dark:text-red-400', label: 'Critique' },
    info: { icon: Info, class: 'text-muted-foreground', label: 'Information' },
} as const;
</script>

<template>
    <section class="rounded-2xl border bg-card p-5">
        <h2 class="font-semibold">Ce qui est significatif</h2>
        <p class="text-sm text-muted-foreground">Constats calculés automatiquement à partir des chiffres du trimestre.</p>
        <ul class="mt-4 space-y-3">
            <li v-for="(item, index) in highlights" :key="index" class="flex gap-2.5 text-sm leading-6">
                <component :is="tones[item.tone].icon" class="mt-1 size-4 shrink-0" :class="tones[item.tone].class" />
                <span
                    ><span class="sr-only">{{ tones[item.tone].label }} : </span>{{ item.text }}</span
                >
            </li>
        </ul>
    </section>
</template>
