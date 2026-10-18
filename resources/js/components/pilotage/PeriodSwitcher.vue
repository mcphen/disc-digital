<script setup lang="ts">
import { Button } from '@/components/ui/button';
import type { Period, PeriodOption } from '@/types/pilotage';
import { router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{ period: Period; periods: PeriodOption[]; href: string }>();

// Les trimestres sont triés du plus récent au plus ancien.
const index = computed(() => props.periods.findIndex((option) => option.key === props.period.key));
const go = (key: string) => router.get(props.href, { periode: key }, { preserveScroll: true });
</script>

<template>
    <div class="flex items-center gap-1.5">
        <Button
            variant="outline"
            size="icon"
            :disabled="index >= periods.length - 1"
            aria-label="Trimestre précédent"
            @click="go(periods[index + 1].key)"
        >
            <ChevronLeft class="size-4" />
        </Button>
        <select
            class="h-10 rounded-md border bg-background px-3 text-sm font-medium"
            aria-label="Trimestre"
            :value="period.key"
            @change="go(($event.target as HTMLSelectElement).value)"
        >
            <option v-for="option in periods" :key="option.key" :value="option.key">
                {{ option.label }}{{ option.is_current ? ' (en cours)' : '' }}
            </option>
        </select>
        <Button variant="outline" size="icon" :disabled="index <= 0" aria-label="Trimestre suivant" @click="go(periods[index - 1].key)">
            <ChevronRight class="size-4" />
        </Button>
    </div>
</template>
