<script setup lang="ts">
import { Check } from 'lucide-vue-next';

/** Sélection multiple sous forme de pastilles (divisions contributrices, co-agents). */
const props = defineProps<{ options: { id: number; label: string; hint?: string }[]; empty?: string }>();
const selected = defineModel<number[]>({ required: true });

const toggle = (id: number) => {
    selected.value = selected.value.includes(id) ? selected.value.filter((value) => value !== id) : [...selected.value, id];
};
</script>

<template>
    <div v-if="props.options.length" class="flex flex-wrap gap-2">
        <button
            v-for="option in props.options"
            :key="option.id"
            type="button"
            :aria-pressed="selected.includes(option.id)"
            :title="option.hint"
            class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm transition-colors"
            :class="selected.includes(option.id) ? 'border-primary bg-primary/10 font-medium' : 'text-muted-foreground hover:bg-accent'"
            @click="toggle(option.id)"
        >
            <Check v-if="selected.includes(option.id)" class="size-3.5" />
            {{ option.label }}
        </button>
    </div>
    <p v-else class="text-sm text-muted-foreground">{{ props.empty ?? 'Aucun choix disponible.' }}</p>
</template>
