<script setup lang="ts">
defineProps<{
    level: 'VERT' | 'JAUNE' | 'ROUGE' | null;
}>();
</script>

<template>
    <span
        v-if="level"
        class="border-current/10 inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-semibold shadow-sm"
        :class="{
            'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300': level === 'VERT',
            'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300': level === 'JAUNE',
            'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300': level === 'ROUGE',
        }"
    >
        <span
            class="mr-1.5 size-2 rounded-full"
            :class="{
                'bg-emerald-500': level === 'VERT',
                'bg-amber-500': level === 'JAUNE',
                'bg-red-500': level === 'ROUGE',
            }"
        />
        {{ level }}
    </span>
    <span v-else class="text-sm text-muted-foreground">—</span>
</template>
