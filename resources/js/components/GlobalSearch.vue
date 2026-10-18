<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import type { SharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { AlertTriangle, FileSearch, Search, ShieldCheck, UserRoundCheck } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const page = usePage<SharedData>();
const open = ref(false);
const query = ref('');

const quickQueues = computed(() => {
    const role = page.props.auth.user.role;
    const counts = page.props.navigation;
    const items = [
        { label: 'Courriers prioritaires', queue: 'priority', count: counts.priority, icon: AlertTriangle },
    ];

    if (['admin', 'director', 'secretariat'].includes(role)) {
        items.unshift({ label: 'Courriers sans division', queue: 'unassigned', count: counts.unassigned, icon: FileSearch });
    }
    if (role === 'division_head') {
        items.unshift({ label: 'Projets à valider', queue: 'review', count: counts.review, icon: ShieldCheck });
        items.unshift({ label: 'Courriers sans agent', queue: 'division_only', count: counts.division_only, icon: UserRoundCheck });
    }
    if (role === 'agent') {
        items.unshift({ label: 'Mes courriers', queue: 'mine', count: counts.mine, icon: UserRoundCheck });
    }
    if (['admin', 'director'].includes(role)) {
        items.unshift({ label: 'En attente de signature', queue: 'signature', count: counts.signature, icon: ShieldCheck });
    }

    return items;
});

const show = () => {
    open.value = true;
};
const search = () => {
    const value = query.value.trim();
    if (!value) return;
    open.value = false;
    router.get('/courriers', { search: value });
};
const goToQueue = (queue: string) => {
    open.value = false;
    router.get('/courriers', { queue });
};
const onKeydown = (event: KeyboardEvent) => {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        show();
    }
};

onMounted(() => window.addEventListener('keydown', onKeydown));
onUnmounted(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Button variant="outline" class="h-10 justify-start gap-2 rounded-xl bg-card/70 px-3 text-muted-foreground shadow-sm" @click="show">
        <Search class="size-4" />
        <span class="hidden lg:inline">Rechercher un courrier…</span>
        <kbd class="hidden rounded-md border bg-muted px-1.5 py-0.5 text-[10px] font-medium xl:inline">Ctrl K</kbd>
    </Button>

    <Dialog v-model:open="open">
        <DialogContent class="gap-0 overflow-hidden p-0 sm:max-w-xl">
            <DialogHeader class="border-b px-5 py-4 text-left">
                <DialogTitle>Recherche rapide</DialogTitle>
                <DialogDescription>Numéro DISC, référence, objet, service d’origine ou numéro d’arrivée.</DialogDescription>
            </DialogHeader>
            <form class="flex items-center gap-2 border-b p-4" @submit.prevent="search">
                <Search class="ml-1 size-5 shrink-0 text-muted-foreground" />
                <Input v-model="query" autofocus class="border-0 bg-transparent text-base shadow-none focus-visible:ring-0" placeholder="Que recherchez-vous ?" />
                <Button type="submit" :disabled="!query.trim()">Rechercher</Button>
            </form>
            <div class="p-3">
                <p class="px-2 pb-2 text-[10px] font-semibold uppercase tracking-[0.16em] text-muted-foreground">Accès rapides</p>
                <button
                    v-for="item in quickQueues"
                    :key="item.queue"
                    type="button"
                    class="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left transition hover:bg-accent"
                    @click="goToQueue(item.queue)"
                >
                    <span class="grid size-9 place-items-center rounded-xl bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                        <component :is="item.icon" class="size-4" />
                    </span>
                    <span class="flex-1 text-sm font-medium">{{ item.label }}</span>
                    <span class="rounded-full bg-muted px-2.5 py-1 text-xs font-semibold">{{ item.count }}</span>
                </button>
            </div>
        </DialogContent>
    </Dialog>
</template>
