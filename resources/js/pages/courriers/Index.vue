<script setup lang="ts">
import AlertBadge from '@/components/disc/AlertBadge.vue';
import MobileDataCard from '@/components/disc/MobileDataCard.vue';
import StatusBadge from '@/components/disc/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { SharedData } from '@/types';
import type { Courrier, CourrierType, Division, Paginated } from '@/types/disc';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ChevronDown, ChevronRight, Eye, FilePlus2, ListChecks, Search, SlidersHorizontal } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

const props = defineProps<{
    courriers: Paginated<Courrier>;
    filters: { search?: string; status?: string; division?: number; type?: number; queue?: string };
    divisions: Division[];
    types: CourrierType[];
    statuses: Record<string, string>;
    canCreate: boolean;
    canBulkAssign: boolean;
}>();

const form = reactive({
    queue: props.filters.queue ?? '',
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    division: props.filters.division?.toString() ?? '',
    type: props.filters.type?.toString() ?? '',
});
const filtersOpen = ref(Boolean(props.filters.search || props.filters.status || props.filters.division || props.filters.type));
const activeFilterCount = computed(() => [form.search, form.status, form.division, form.type].filter(Boolean).length);

const applyFilters = () => router.get('/courriers', form, { preserveState: true, replace: true });
const resetFilters = () => {
    form.queue = '';
    form.search = '';
    form.status = '';
    form.division = '';
    form.type = '';
    applyFilters();
};

const breadcrumbs = [{ title: 'Courriers', href: '/courriers' }];
const page = usePage<SharedData>();
const queueOptions = computed(() => {
    const role = page.props.auth.user.role;
    const counts = page.props.navigation;
    const options = [{ value: '', label: 'Tous', count: null as number | null }];

    if (['admin', 'director', 'secretariat'].includes(role)) options.push({ value: 'unassigned', label: 'Sans division', count: counts.unassigned });
    if (role === 'division_head') {
        options.push({ value: 'division_only', label: 'Sans agent', count: counts.division_only });
        options.push({ value: 'review', label: 'À valider', count: counts.review });
    }
    if (role === 'agent') {
        options.push({ value: 'mine', label: 'Mes courriers', count: counts.mine });
        options.push({ value: 'corrections', label: 'À corriger', count: counts.corrections });
    }
    if (['admin', 'director'].includes(role)) options.push({ value: 'signature', label: 'À signer', count: counts.signature });
    options.push({ value: 'priority', label: 'Priorités', count: counts.priority });

    return options;
});
const dateFormat = new Intl.DateTimeFormat('fr-SN', { dateStyle: 'medium', timeZone: 'Africa/Dakar' });
const formatDate = (value: string) => dateFormat.format(new Date(`${value}T00:00:00`));
const detailUrl = (id: number) => `/courriers/${id}?return=${encodeURIComponent(page.url)}`;
const openCourrier = (id: number) => router.visit(detailUrl(id));
</script>

<template>
    <Head title="Courriers" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-5 p-4 md:p-6">
            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Courriers</h1>
                    <p class="text-sm text-muted-foreground">{{ courriers.total }} courrier(s) accessible(s).</p>
                </div>
                <div class="grid grid-cols-1 gap-2 min-[430px]:grid-cols-2 sm:flex">
                    <Button v-if="canBulkAssign" variant="outline" class="w-full sm:w-auto" as-child>
                        <Link href="/courriers/bulk-assignment"><ListChecks class="mr-2 size-4" /> Imputation en masse</Link>
                    </Button>
                    <Button v-if="canCreate" class="w-full sm:w-auto" as-child>
                        <Link href="/courriers/create"><FilePlus2 class="mr-2 size-4" /> Nouveau courrier</Link>
                    </Button>
                </div>
            </div>

            <nav class="flex gap-2 overflow-x-auto pb-1" aria-label="Files de travail">
                <Link
                    v-for="queue in queueOptions"
                    :key="queue.value || 'all'"
                    :href="queue.value ? `/courriers?queue=${queue.value}` : '/courriers'"
                    class="inline-flex shrink-0 items-center gap-2 rounded-full border px-3.5 py-2 text-sm font-medium transition"
                    :class="
                        form.queue === queue.value
                            ? 'border-amber-400 bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200'
                            : 'bg-card hover:bg-accent'
                    "
                >
                    {{ queue.label }}
                    <span v-if="queue.count !== null" class="rounded-full bg-background/80 px-2 py-0.5 text-xs">{{ queue.count }}</span>
                </Link>
            </nav>

            <form class="grid gap-3 rounded-xl border bg-card p-4 lg:grid-cols-[2fr_1fr_1fr_1fr_auto]" @submit.prevent="applyFilters">
                <button
                    type="button"
                    class="flex min-h-10 items-center justify-between gap-3 text-left lg:hidden"
                    @click="filtersOpen = !filtersOpen"
                >
                    <span class="flex items-center gap-2 text-sm font-semibold"><SlidersHorizontal class="size-4 text-amber-600" /> Filtres</span>
                    <span class="flex items-center gap-2 text-xs text-muted-foreground">
                        {{ activeFilterCount ? `${activeFilterCount} actif(s)` : 'Afficher' }}
                        <ChevronDown class="size-4 transition-transform" :class="filtersOpen ? 'rotate-180' : ''" />
                    </span>
                </button>
                <div class="gap-3 lg:contents" :class="filtersOpen ? 'grid' : 'hidden lg:contents'">
                    <div class="relative">
                        <Search class="absolute left-3 top-3 size-4 text-muted-foreground" /><Input
                            v-model="form.search"
                            class="pl-9"
                            placeholder="Référence, objet, service..."
                        />
                    </div>
                    <select v-model="form.status" class="h-10 rounded-md border bg-background px-3 text-sm">
                        <option value="">Tous les états</option>
                        <option v-for="(label, status) in statuses" :key="status" :value="status">{{ label }}</option>
                    </select>
                    <select v-model="form.division" class="h-10 rounded-md border bg-background px-3 text-sm">
                        <option value="">Toutes les divisions</option>
                        <option v-for="division in divisions" :key="division.id" :value="division.id">{{ division.code }}</option>
                    </select>
                    <select v-model="form.type" class="h-10 rounded-md border bg-background px-3 text-sm">
                        <option value="">Tous les types</option>
                        <option v-for="type in types" :key="type.id" :value="type.id">{{ type.name }}</option>
                    </select>
                    <div class="flex gap-2">
                        <Button type="submit" variant="outline" class="flex-1 lg:flex-none"><SlidersHorizontal class="mr-2 size-4" /> Filtrer</Button
                        ><Button type="button" variant="ghost" @click="resetFilters">Effacer</Button>
                    </div>
                </div>
            </form>

            <div
                class="flex flex-wrap items-center gap-x-5 gap-y-2 rounded-2xl border border-border/60 bg-card/60 px-4 py-3 text-xs text-muted-foreground"
            >
                <span class="font-semibold text-foreground">Lecture des alertes :</span>
                <span class="flex items-center gap-2"><i class="size-2 rounded-full bg-emerald-500" /> Dans les temps</span>
                <span class="flex items-center gap-2"><i class="size-2 rounded-full bg-amber-500" /> Vigilance</span>
                <span class="flex items-center gap-2"><i class="size-2 rounded-full bg-red-500" /> Intervention prioritaire</span>
            </div>

            <div class="overflow-hidden rounded-xl border bg-card">
                <div class="hidden overflow-x-auto md:block">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b bg-muted/50 text-xs uppercase text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3">N° DISC</th>
                                <th class="px-4 py-3">Arrivée</th>
                                <th class="px-4 py-3">Courrier</th>
                                <th class="px-4 py-3">Imputation</th>
                                <th class="px-4 py-3">État</th>
                                <th class="px-4 py-3">Alerte</th>
                                <th class="px-4 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="courrier in courriers.data"
                                :key="courrier.id"
                                role="link"
                                tabindex="0"
                                class="cursor-pointer transition-colors hover:bg-muted/50 focus-visible:bg-muted/50 focus-visible:outline-none"
                                :aria-label="`Afficher le détail du courrier ${courrier.registration_number}`"
                                @click="openCourrier(courrier.id)"
                                @keydown.enter="openCourrier(courrier.id)"
                            >
                                <td class="whitespace-nowrap px-4 py-4 font-medium">
                                    <Link :href="detailUrl(courrier.id)" class="text-amber-700 hover:underline dark:text-amber-300" @click.stop>{{
                                        courrier.registration_number
                                    }}</Link>
                                    <p v-if="courrier.arrival_number" class="mt-1 text-xs font-normal text-muted-foreground">
                                        Arrivée {{ courrier.arrival_number }}
                                    </p>
                                </td>
                                <td class="whitespace-nowrap px-4 py-4">{{ formatDate(courrier.arrival_date) }}</td>
                                <td class="max-w-md px-4 py-4">
                                    <p class="line-clamp-2 font-medium">{{ courrier.subject }}</p>
                                    <p class="mt-1 truncate text-xs text-muted-foreground">
                                        {{ courrier.origin_service }}<span v-if="courrier.reference"> · {{ courrier.reference }}</span>
                                    </p>
                                </td>
                                <td class="px-4 py-4">
                                    <p>
                                        {{ courrier.division?.code ?? '—' }}
                                        <span
                                            v-if="courrier.contributing_divisions.length"
                                            class="text-xs text-muted-foreground"
                                            :title="`En contribution : ${courrier.contributing_divisions.map((division) => division.code).join(', ')}`"
                                            >+{{ courrier.contributing_divisions.length }}</span
                                        >
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ courrier.assigned_agent?.name ?? courrier.legacy_agent_name ?? 'Non imputé' }}
                                    </p>
                                </td>
                                <td class="px-4 py-4"><StatusBadge :status="courrier.status" :label="courrier.status_label" /></td>
                                <td class="px-4 py-4"><AlertBadge :level="courrier.metrics.current_alert" /></td>
                                <td class="whitespace-nowrap px-4 py-4 text-right" @click.stop>
                                    <Button size="sm" variant="outline" as-child>
                                        <Link :href="detailUrl(courrier.id)"> <Eye class="mr-2 size-4" /> Voir le détail </Link>
                                    </Button>
                                </td>
                            </tr>
                            <tr v-if="!courriers.data.length">
                                <td colspan="7" class="px-4 py-12 text-center text-muted-foreground">Aucun courrier ne correspond aux critères.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="disc-mobile-stack md:hidden">
                    <Link v-for="courrier in courriers.data" :key="courrier.id" :href="detailUrl(courrier.id)" class="block">
                        <MobileDataCard interactive>
                            <template #header>
                                <p class="font-semibold text-amber-700 dark:text-amber-300">{{ courrier.registration_number }}</p>
                                <p class="mt-1 truncate text-xs text-muted-foreground">
                                    {{ formatDate(courrier.arrival_date) }} · {{ courrier.origin_service }}
                                </p>
                            </template>
                            <template #badge><AlertBadge :level="courrier.metrics.current_alert" /></template>
                            <p class="line-clamp-3 text-sm font-semibold leading-relaxed">{{ courrier.subject }}</p>
                            <template #footer>
                                <div class="flex items-center justify-between gap-3">
                                    <div class="min-w-0 space-y-2">
                                        <StatusBadge :status="courrier.status" :label="courrier.status_label" />
                                        <p class="truncate text-xs text-muted-foreground">
                                            {{ courrier.division?.code ?? 'Sans division' }} ·
                                            {{ courrier.assigned_agent?.name ?? courrier.legacy_agent_name ?? 'Aucun agent' }}
                                        </p>
                                    </div>
                                    <ChevronRight class="size-5 shrink-0 text-muted-foreground" />
                                </div>
                            </template>
                        </MobileDataCard>
                    </Link>
                    <div v-if="!courriers.data.length" class="px-4 py-12 text-center text-sm text-muted-foreground">
                        Aucun courrier ne correspond aux critères.
                    </div>
                </div>
                <div v-if="courriers.last_page > 1" class="flex flex-col items-center justify-between gap-3 border-t px-4 py-3 sm:flex-row">
                    <p class="text-sm text-muted-foreground">Résultats {{ courriers.from }} à {{ courriers.to }} sur {{ courriers.total }}</p>
                    <div class="flex max-w-full flex-nowrap gap-1 overflow-x-auto pb-1 sm:flex-wrap sm:overflow-visible sm:pb-0">
                        <Button
                            v-for="link in courriers.links"
                            :key="link.label"
                            size="sm"
                            :variant="link.active ? 'default' : 'outline'"
                            :disabled="!link.url"
                            class="shrink-0"
                            as-child
                            ><Link :href="link.url ?? '#'" preserve-scroll v-html="link.label"
                        /></Button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
