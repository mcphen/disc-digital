<script setup lang="ts">
interface Props {
    class?: string;
}

defineProps<Props>();
</script>

<template>
    <div
        class="flex aspect-square size-11 items-center justify-center rounded-full bg-white p-0.5 shadow-lg shadow-black/25 ring-1 ring-amber-300/40"
    >
        <img src="/images/logo-tresor-public.png" alt="Trésor public du Sénégal" class="size-full object-contain" />
    </div>
    <div class="ml-2 grid flex-1 text-left text-sm">
        <span class="mb-1 truncate font-semibold leading-none tracking-[-0.02em] text-white">Trésor Public</span>
        <span class="truncate text-[10px] font-medium uppercase tracking-[0.14em] text-amber-300">DISC Digital</span>
    </div>
</template>
