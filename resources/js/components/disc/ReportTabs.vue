<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { FileBarChart, Gauge } from 'lucide-vue-next';

defineProps<{ current: 'tracking' | 'performance' }>();

const tabs = [
    { value: 'tracking', label: 'Suivi des courriers', href: '/reports', icon: FileBarChart },
    { value: 'performance', label: 'Performance', href: '/reports/performance', icon: Gauge },
] as const;
</script>

<template>
    <nav class="inline-flex gap-1 rounded-2xl bg-muted p-1.5 print:hidden" aria-label="Rapports">
        <Link
            v-for="tab in tabs"
            :key="tab.value"
            :href="tab.href"
            :aria-current="current === tab.value ? 'page' : undefined"
            :class="[
                'flex items-center gap-1.5 rounded-xl px-3.5 py-2 text-sm transition-colors',
                current === tab.value ? 'bg-background font-medium text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground',
            ]"
        >
            <component :is="tab.icon" class="size-4" />
            {{ tab.label }}
        </Link>
    </nav>
</template>
