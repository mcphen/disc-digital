<script setup lang="ts">
import { SidebarInset } from '@/components/ui/sidebar';
import { computed } from 'vue';

interface Props {
    variant?: 'header' | 'sidebar';
    class?: string;
}

const props = defineProps<Props>();
const className = computed(() => props.class);
</script>

<template>
    <SidebarInset
        v-if="props.variant === 'sidebar'"
        :class="[
            'overflow-hidden bg-background/95 text-foreground backdrop-blur-xl md:rounded-[28px] md:border md:border-white/50 md:shadow-[0_24px_80px_-40px_rgba(15,23,42,0.45)] dark:md:border-white/5',
            className,
        ]"
    >
        <slot />
    </SidebarInset>
    <main v-else class="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-4 rounded-xl" :class="className">
        <slot />
    </main>
</template>
