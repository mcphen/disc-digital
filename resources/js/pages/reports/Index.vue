<script setup lang="ts">
import AlertBadge from '@/components/disc/AlertBadge.vue';
import MobileDataCard from '@/components/disc/MobileDataCard.vue';
import ReportTabs from '@/components/disc/ReportTabs.vue';
import StatusBadge from '@/components/disc/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { CourrierType, Division } from '@/types/disc';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowUpRight, Download, Printer, SlidersHorizontal } from 'lucide-vue-next';
import { computed, reactive } from 'vue';

interface Breakdown {
    label: string;
    count: number;
}

interface ReportRow {
    id: number;
    registration_number: string;
    arrival_date: string;
    origin_service: string;
    subject: string;
    type: string;
    division: string | null;
    agent: string | null;
    status: string;
    alert: 'VERT' | 'JAUNE' | 'ROUGE' | null;
    due_date: string | null;
    observation: string | null;
}

const props = defineProps<{
    filters: { date_from?: string; date_to?: string; division?: number; type?: number; status?: string };
    summary: {
        total: number;
        completed: number;
        in_progress: number;
        overdue: number;
        by_division: Breakdown[];
        by_type: Breakdown[];
        by_status: Breakdown[];
    };
    rows: ReportRow[];
    isTruncated: boolean;
    divisions: Division[];
    types: CourrierType[];
    statuses: Record<string, string>;
}>();

const form = reactive({
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    division: props.filters.division?.toString() ?? '',
    type: props.filters.type?.toString() ?? '',
    status: props.filters.status ?? '',
});
const applyFilters = () => router.get('/reports', form, { preserveState: true, replace: true });
const exportUrl = computed(() => {
    const params = new URLSearchParams();
    Object.entries(form).forEach(([key, value]) => value && params.set(key, value));
    return `/reports/export?${params.toString()}`;
});
const formatDate = (value: string | null) =>
    value ? new Intl.DateTimeFormat('fr-SN', { dateStyle: 'medium', timeZone: 'Africa/Dakar' }).format(new Date(`${value}T00:00:00`)) : '—';
const printReport = () => window.print();
const breadcrumbs = [{ title: 'Rapports', href: '/reports' }];
</script>

<template>
    <Head title="Rapports" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-5 p-4 md:p-6 print:p-0">
            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-2xl font-semibold">Rapport de suivi des courriers</h1>
                    <p class="text-sm text-muted-foreground">Analyse, export Excel compatible et impression PDF.</p>
                </div>
                <ReportTabs current="tracking" class="sm:ml-auto sm:mr-2" />
                <div class="flex flex-col gap-2 sm:flex-row print:hidden">
                    <Button variant="outline" as-child
                        ><a :href="exportUrl"><Download class="mr-2 size-4" /> Export CSV</a></Button
                    >
                    <Button variant="outline" @click="printReport"><Printer class="mr-2 size-4" /> Imprimer / PDF</Button>
                </div>
            </div>

            <form class="grid gap-3 rounded-xl border bg-card p-4 lg:grid-cols-6 print:hidden" @submit.prevent="applyFilters">
                <Input v-model="form.date_from" type="date" aria-label="Date de début" />
                <Input v-model="form.date_to" type="date" aria-label="Date de fin" />
                <select v-model="form.division" class="h-10 rounded-md border bg-background px-3 text-sm">
                    <option value="">Toutes divisions</option>
                    <option v-for="division in divisions" :key="division.id" :value="division.id">{{ division.code }}</option>
                </select>
                <select v-model="form.type" class="h-10 rounded-md border bg-background px-3 text-sm">
                    <option value="">Tous les types</option>
                    <option v-for="type in types" :key="type.id" :value="type.id">{{ type.name }}</option>
                </select>
                <select v-model="form.status" class="h-10 rounded-md border bg-background px-3 text-sm">
                    <option value="">Tous les statuts</option>
                    <option v-for="(label, status) in statuses" :key="status" :value="status">{{ label }}</option>
                </select>
                <Button><SlidersHorizontal class="mr-2 size-4" /> Appliquer</Button>
            </form>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border bg-card p-4">
                    <p class="text-sm text-muted-foreground">Total</p>
                    <p class="mt-1 text-3xl font-semibold">{{ summary.total }}</p>
                </div>
                <div class="rounded-xl border bg-card p-4">
                    <p class="text-sm text-muted-foreground">Clôturés</p>
                    <p class="mt-1 text-3xl font-semibold text-emerald-600">{{ summary.completed }}</p>
                </div>
                <div class="rounded-xl border bg-card p-4">
                    <p class="text-sm text-muted-foreground">En cours</p>
                    <p class="mt-1 text-3xl font-semibold text-amber-700 dark:text-amber-300">{{ summary.in_progress }}</p>
                </div>
                <div class="rounded-xl border bg-card p-4">
                    <p class="text-sm text-muted-foreground">En retard</p>
                    <p class="mt-1 text-3xl font-semibold text-red-600">{{ summary.overdue }}</p>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                <section
                    v-for="section in [
                        { title: 'Par division', data: summary.by_division },
                        { title: 'Par type', data: summary.by_type },
                        { title: 'Par statut', data: summary.by_status },
                    ]"
                    :key="section.title"
                    class="rounded-xl border bg-card p-4"
                >
                    <h2 class="font-semibold">{{ section.title }}</h2>
                    <div class="mt-4 space-y-3">
                        <div v-for="item in section.data" :key="item.label" class="flex items-center justify-between gap-3 text-sm">
                            <span class="truncate text-muted-foreground">{{ statuses[item.label] ?? item.label }}</span
                            ><strong>{{ item.count }}</strong>
                        </div>
                    </div>
                </section>
            </div>

            <section class="overflow-hidden rounded-xl border bg-card">
                <div class="hidden overflow-x-auto lg:block print:block">
                    <table class="w-full min-w-[1000px] text-left text-sm">
                        <thead class="border-b bg-muted/50 text-xs uppercase text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3">N° DISC</th>
                                <th class="px-4 py-3">Arrivée</th>
                                <th class="px-4 py-3">Courrier</th>
                                <th class="px-4 py-3">Division / Agent</th>
                                <th class="px-4 py-3">Statut</th>
                                <th class="px-4 py-3">Alerte</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="row in rows" :key="row.id">
                                <td class="px-4 py-3 font-medium">
                                    <Link class="text-amber-700 hover:underline dark:text-amber-300" :href="`/courriers/${row.id}`">{{
                                        row.registration_number
                                    }}</Link>
                                </td>
                                <td class="px-4 py-3">{{ formatDate(row.arrival_date) }}</td>
                                <td class="max-w-md px-4 py-3">
                                    <p class="line-clamp-2 font-medium">{{ row.subject }}</p>
                                    <p class="text-xs text-muted-foreground">{{ row.origin_service }} · {{ row.type }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <p>{{ row.division ?? '—' }}</p>
                                    <p class="text-xs text-muted-foreground">{{ row.agent ?? 'Non affecté' }}</p>
                                </td>
                                <td class="px-4 py-3"><StatusBadge :status="row.status" :label="statuses[row.status] ?? row.status" /></td>
                                <td class="px-4 py-3"><AlertBadge :level="row.alert" /></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="disc-mobile-stack lg:hidden print:hidden">
                    <MobileDataCard v-for="row in rows" :key="row.id">
                        <template #header>
                            <Link class="font-semibold text-amber-700 dark:text-amber-300" :href="`/courriers/${row.id}`">
                                {{ row.registration_number }}
                            </Link>
                            <p class="mt-1 text-xs text-muted-foreground">{{ formatDate(row.arrival_date) }} · {{ row.origin_service }}</p>
                        </template>
                        <template #badge><AlertBadge :level="row.alert" /></template>

                        <p class="line-clamp-3 text-sm font-semibold">{{ row.subject }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">{{ row.type }}</p>

                        <template #footer>
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <StatusBadge :status="row.status" :label="statuses[row.status] ?? row.status" />
                                <p class="text-right text-xs text-muted-foreground">
                                    {{ row.division ?? 'Sans division' }} · {{ row.agent ?? 'Non affecté' }}
                                </p>
                            </div>
                            <Button variant="outline" class="mt-3 w-full" as-child>
                                <Link :href="`/courriers/${row.id}`">Consulter le courrier <ArrowUpRight class="ml-2 size-4" /></Link>
                            </Button>
                        </template>
                    </MobileDataCard>
                    <div v-if="!rows.length" class="px-4 py-12 text-center text-sm text-muted-foreground">Aucun résultat pour ces filtres.</div>
                </div>
                <p v-if="isTruncated" class="border-t px-4 py-3 text-xs text-muted-foreground">
                    Aperçu limité aux 100 premiers résultats. L’export CSV contient toutes les lignes.
                </p>
            </section>
        </div>
    </AppLayout>
</template>
