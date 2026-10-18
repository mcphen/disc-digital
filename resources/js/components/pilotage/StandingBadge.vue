<script setup lang="ts">
import { standingMeta } from '@/lib/pilotage';
import type { Standing } from '@/types/pilotage';
import { computed } from 'vue';

const props = defineProps<{ standing: Standing; compact?: boolean }>();
const meta = computed(() => standingMeta[props.standing]);
</script>

<template>
    <span
        class="inline-flex items-center gap-1 whitespace-nowrap rounded-full font-semibold"
        :class="[meta.badge, compact ? 'px-1.5 py-0.5 text-[10px]' : 'px-2.5 py-1 text-xs']"
        :title="meta.label"
    >
        <component :is="meta.icon" :class="compact ? 'size-3' : 'size-3.5'" />
        <span :class="compact ? 'sr-only sm:not-sr-only' : ''">{{ meta.label }}</span>
    </span>
</template>
