<script setup lang="ts">
withDefaults(
    defineProps<{
        interactive?: boolean;
        selected?: boolean;
    }>(),
    {
        interactive: false,
        selected: false,
    },
);
</script>

<template>
    <article class="disc-mobile-card" :class="[interactive ? 'disc-mobile-card--interactive' : '', selected ? 'disc-mobile-card--selected' : '']">
        <header v-if="$slots.header || $slots.badge" class="flex items-start justify-between gap-3">
            <div class="min-w-0 flex-1"><slot name="header" /></div>
            <div v-if="$slots.badge" class="shrink-0"><slot name="badge" /></div>
        </header>

        <div v-if="$slots.default" class="mt-3"><slot /></div>

        <footer v-if="$slots.footer" class="mt-4 border-t border-border/60 pt-3">
            <slot name="footer" />
        </footer>
    </article>
</template>
