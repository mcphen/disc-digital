<script setup lang="ts">
import { days, formatDate, percent } from '@/lib/pilotage';
import type { Activity, Period } from '@/types/pilotage';
import { computed } from 'vue';

const props = defineProps<{ activity: Activity; period: Period; mode: 'direction' | 'division' | 'agent' }>();

const tiles = computed(() => {
    const a = props.activity;
    const stockLabel = props.period.is_current ? "en stock aujourd'hui" : `en stock au ${formatDate(a.backlog_date)}`;

    return [
        {
            label: props.mode === 'agent' ? 'Courriers attribués' : props.mode === 'division' ? 'Courriers imputés' : 'Courriers reçus',
            value: a.received.toString(),
            hint: `au ${props.period.label}`,
        },
        { label: 'Projets rendus', value: a.produced.toString(), hint: `rédaction médiane ${days(a.median_drafting_days)}` },
        props.mode === 'agent'
            ? { label: 'Renvoyés en correction', value: percent(a.correction_rate), hint: 'des projets rendus' }
            : { label: 'Courriers signés', value: a.signed.toString(), hint: `au ${props.period.label}` },
        { label: props.mode === 'agent' ? 'Dossiers en main' : 'Courriers en cours', value: a.backlog.toString(), hint: stockLabel },
    ];
});
</script>

<template>
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div v-for="tile in tiles" :key="tile.label" class="rounded-2xl border bg-card p-4">
            <p class="text-sm text-muted-foreground">{{ tile.label }}</p>
            <p class="mt-1 text-3xl font-semibold tabular-nums">{{ tile.value }}</p>
            <p class="mt-1 text-xs text-muted-foreground">{{ tile.hint }}</p>
        </div>
    </div>
</template>
