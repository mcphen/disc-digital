<script setup lang="ts">
defineProps<{ status: string; label: string }>();
</script>

<template>
    <span
        class="border-current/10 inline-flex rounded-full border px-2.5 py-1 text-[11px] font-semibold shadow-sm"
        :class="{
            'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200': status === 'registered',
            'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300': ['assigned_division', 'assigned_agent'].includes(status),
            'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300': ['pending_division_review', 'submitted'].includes(status),
            'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300': status === 'correction_requested',
            'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300': [
                'division_validated',
                'signed',
                'closed_no_response',
            ].includes(status),
        }"
    >
        {{ label }}
    </span>
</template>
