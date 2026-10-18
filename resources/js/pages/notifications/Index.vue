<script setup lang="ts">
import AlertBadge from '@/components/disc/AlertBadge.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Paginated } from '@/types/disc';
import { Head, Link, router } from '@inertiajs/vue3';
import { Bell, CheckCheck } from 'lucide-vue-next';

interface NotificationItem {
    id: number;
    level: string;
    message: string;
    read_at: string | null;
    created_at: string;
    courrier: { id: number; registration_number: string };
}

const props = defineProps<{
    notifications: Paginated<NotificationItem>;
    filters: { category: string };
    counts: Record<'unread' | 'actions' | 'deadlines' | 'validations' | 'read' | 'all', number>;
}>();
const read = (id: number) => router.put(`/notifications/${id}/read`);
const readAll = () => router.put('/notifications/read-all');
const dateTime = new Intl.DateTimeFormat('fr-SN', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Africa/Dakar' });
const breadcrumbs = [{ title: 'Notifications', href: '/notifications' }];
const asAlertLevel = (level: string): 'JAUNE' | 'ROUGE' | null => (level === 'JAUNE' || level === 'ROUGE' ? level : null);
const workflowLabels: Record<string, string> = {
    REVIEW: 'À valider',
    CORRECT: 'Correction',
    APPROVED: 'Validé',
};
const categories = [
    { value: 'unread', label: 'Non lues' },
    { value: 'actions', label: 'Actions' },
    { value: 'deadlines', label: 'Délais' },
    { value: 'validations', label: 'Validations' },
    { value: 'read', label: 'Lues' },
    { value: 'all', label: 'Toutes' },
] as const;
</script>

<template>
    <Head title="Notifications" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-5 p-4 md:p-6">
            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-2xl font-semibold">Notifications</h1>
                    <p class="text-sm text-muted-foreground">Alertes de délai et actions liées au circuit de validation.</p>
                </div>
                <Button variant="outline" class="w-full sm:w-auto" @click="readAll"><CheckCheck class="mr-2 size-4" /> Tout marquer comme lu</Button>
            </div>

            <nav class="flex gap-2 overflow-x-auto pb-1" aria-label="Catégories de notifications">
                <Link
                    v-for="category in categories"
                    :key="category.value"
                    :href="`/notifications?category=${category.value}`"
                    class="inline-flex shrink-0 items-center gap-2 rounded-full border px-3.5 py-2 text-sm font-medium transition"
                    :class="
                        filters.category === category.value
                            ? 'border-amber-400 bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200'
                            : 'bg-card hover:bg-accent'
                    "
                >
                    {{ category.label }}
                    <span class="rounded-full bg-background/80 px-2 py-0.5 text-xs">{{ counts[category.value] }}</span>
                </Link>
            </nav>

            <div v-if="notifications.data.length" class="space-y-3">
                <article
                    v-for="notification in notifications.data"
                    :key="notification.id"
                    class="flex flex-col gap-3 rounded-xl border p-4 sm:flex-row sm:items-center"
                    :class="notification.read_at ? 'bg-card' : 'border-amber-300 bg-amber-50/50 dark:border-amber-900 dark:bg-amber-950/20'"
                >
                    <div class="rounded-full bg-muted p-2"><Bell class="size-4" /></div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-medium">{{ notification.message }}</p>
                            <AlertBadge v-if="asAlertLevel(notification.level)" :level="asAlertLevel(notification.level)" />
                            <span
                                v-else
                                class="rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-semibold text-amber-800 dark:bg-amber-950 dark:text-amber-300"
                            >
                                {{ workflowLabels[notification.level] ?? notification.level }}
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ dateTime.format(new Date(notification.created_at)) }} · Courrier {{ notification.courrier.registration_number }}
                        </p>
                    </div>
                    <Button size="sm" class="w-full sm:w-auto" :variant="notification.read_at ? 'outline' : 'default'" @click="read(notification.id)">
                        Ouvrir le courrier
                    </Button>
                </article>
            </div>
            <div v-else class="rounded-xl border bg-card p-12 text-center text-muted-foreground">
                <Bell class="mx-auto mb-3 size-8" />
                Aucune notification.
            </div>

            <div v-if="notifications.last_page > 1" class="flex flex-wrap justify-center gap-1">
                <Button
                    v-for="link in notifications.links"
                    :key="link.label"
                    size="sm"
                    :variant="link.active ? 'default' : 'outline'"
                    :disabled="!link.url"
                    as-child
                >
                    <Link :href="link.url ?? '#'" preserve-scroll v-html="link.label" />
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
