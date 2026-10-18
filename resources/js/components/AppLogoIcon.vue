<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({ inheritAttrs: false });
defineProps<{ className?: HTMLAttributes['class'] }>();
</script>

<template>
    <svg viewBox="0 0 32 32" fill="none" :class="className" v-bind="$attrs" aria-hidden="true">
        <path
            d="M6.5 8.75A2.75 2.75 0 0 1 9.25 6h13.5a2.75 2.75 0 0 1 2.75 2.75v14.5A2.75 2.75 0 0 1 22.75 26H9.25a2.75 2.75 0 0 1-2.75-2.75V8.75Z"
            stroke="currentColor"
            stroke-width="2.2"
        />
        <path d="m8 10 8 6 8-6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
        <path d="m17.5 21 2 2 4-4.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
</template>
