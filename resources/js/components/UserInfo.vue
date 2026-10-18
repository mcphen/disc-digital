<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import type { User } from '@/types';
import { computed } from 'vue';

interface Props {
    user: User;
    showEmail?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    showEmail: false,
});

const { getInitials } = useInitials();

// Compute whether we should show the avatar image
const showAvatar = computed(() => props.user.avatar && props.user.avatar !== '');
const roleLabels: Record<string, string> = {
    admin: 'Administrateur',
    director: 'Direction',
    secretariat: 'Secrétariat',
    division_head: 'Chef de division',
    agent: 'Agent',
    auditor: 'Auditeur',
};
</script>

<template>
    <Avatar class="h-9 w-9 overflow-hidden rounded-xl ring-1 ring-white/10">
        <AvatarImage v-if="showAvatar" :src="user.avatar!" :alt="user.name" />
        <AvatarFallback class="rounded-xl bg-gradient-to-br from-amber-300 to-amber-600 font-semibold text-stone-950">
            {{ getInitials(user.name) }}
        </AvatarFallback>
    </Avatar>

    <div class="grid flex-1 text-left text-sm leading-tight">
        <span class="truncate font-medium">{{ user.name }}</span>
        <span class="truncate text-xs text-muted-foreground">{{ showEmail ? user.email : (roleLabels[user.role] ?? user.role) }}</span>
    </div>
</template>
