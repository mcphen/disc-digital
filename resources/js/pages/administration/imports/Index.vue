<script setup lang="ts">
import MobileDataCard from '@/components/disc/MobileDataCard.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { CalendarDays, CheckCircle2, FileSearch, Upload, UserRound } from 'lucide-vue-next';

interface Preview {
    token: string;
    original_name: string;
    total: number;
    valid: number;
    duplicates: number;
    invalid: number;
    errors: Array<{ row: number; messages: string[] }>;
    preview: Array<{
        row: number;
        arrival_date: string;
        reference: string | null;
        origin_service: string;
        subject: string;
        type: string;
        division: string | null;
        agent: string | null;
        duplicate: boolean;
    }>;
}

interface ImportHistory {
    id: number;
    original_name: string;
    total_rows: number;
    imported_rows: number;
    skipped_rows: number;
    invalid_rows: number;
    imported_at: string;
    imported_by: { id: number; name: string };
}

defineProps<{ preview: Preview | null; imports: ImportHistory[] }>();
const page = usePage<SharedData>();
const uploadForm = useForm<{ file: File | null }>({ file: null });
const confirmForm = useForm({ token: '' });
const analyze = () => uploadForm.post('/administration/imports/preview', { forceFormData: true });
const selectFile = (event: Event) => {
    uploadForm.file = (event.target as HTMLInputElement).files?.[0] ?? null;
};
const confirmImport = (token: string) => {
    confirmForm.token = token;
    confirmForm.post('/administration/imports');
};
const breadcrumbs = [
    { title: 'Administration', href: '/administration/imports' },
    { title: 'Import Excel', href: '/administration/imports' },
];
const dateTime = new Intl.DateTimeFormat('fr-SN', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Africa/Dakar' });
</script>

<template>
    <Head title="Import Excel" />
    <AppLayout :breadcrumbs="breadcrumbs"
        ><div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold">Import du suivi Excel</h1>
                <p class="text-sm text-muted-foreground">Analysez le classeur avant toute écriture dans la base MySQL.</p>
            </div>
            <div v-if="page.props.flash.success" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ page.props.flash.success }}
            </div>
            <div v-if="page.props.flash.error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                {{ page.props.flash.error }}
            </div>
            <section class="rounded-xl border bg-card p-5 md:p-6">
                <div class="flex items-center gap-3">
                    <div class="rounded-lg bg-amber-100 p-2 text-amber-700"><FileSearch class="size-5" /></div>
                    <div>
                        <h2 class="font-semibold">1. Sélectionner et analyser</h2>
                        <p class="text-sm text-muted-foreground">Format attendu : classeur XLSX contenant l’onglet « Suivi Courrier ».</p>
                    </div>
                </div>
                <form class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center" @submit.prevent="analyze">
                    <input
                        type="file"
                        accept=".xlsx"
                        required
                        class="block flex-1 rounded-md border bg-background p-2 text-sm"
                        @change="selectFile"
                    /><Button :disabled="uploadForm.processing"><Upload class="mr-2 size-4" /> Analyser le fichier</Button>
                </form>
                <p v-if="uploadForm.errors.file" class="mt-2 text-sm text-red-600">{{ uploadForm.errors.file }}</p>
            </section>
            <section v-if="preview" class="rounded-xl border bg-card p-5 md:p-6">
                <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                    <div>
                        <h2 class="font-semibold">2. Rapport de pré-import</h2>
                        <p class="text-sm text-muted-foreground">{{ preview.original_name }}</p>
                    </div>
                    <Button :disabled="confirmForm.processing || preview.valid === 0" @click="confirmImport(preview.token)"
                        ><CheckCircle2 class="mr-2 size-4" /> Confirmer {{ preview.valid }} ligne(s)</Button
                    >
                </div>
                <div class="mt-5 grid gap-3 sm:grid-cols-4">
                    <div class="rounded-lg bg-muted p-3">
                        <p class="text-2xl font-semibold">{{ preview.total }}</p>
                        <p class="text-xs text-muted-foreground">Lignes détectées</p>
                    </div>
                    <div class="rounded-lg bg-emerald-50 p-3 text-emerald-800">
                        <p class="text-2xl font-semibold">{{ preview.valid }}</p>
                        <p class="text-xs">Valides à importer</p>
                    </div>
                    <div class="rounded-lg bg-amber-50 p-3 text-amber-800">
                        <p class="text-2xl font-semibold">{{ preview.duplicates }}</p>
                        <p class="text-xs">Doublons ignorés</p>
                    </div>
                    <div class="rounded-lg bg-red-50 p-3 text-red-800">
                        <p class="text-2xl font-semibold">{{ preview.invalid }}</p>
                        <p class="text-xs">Lignes invalides</p>
                    </div>
                </div>
                <div v-if="preview.errors.length" class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4">
                    <h3 class="text-sm font-semibold text-red-800">Erreurs à corriger</h3>
                    <ul class="mt-2 space-y-1 text-sm text-red-700">
                        <li v-for="error in preview.errors" :key="error.row">Ligne {{ error.row }} : {{ error.messages.join(' ') }}</li>
                    </ul>
                </div>
                <div class="mt-5 hidden overflow-x-auto md:block">
                    <table class="w-full min-w-[850px] text-sm">
                        <thead>
                            <tr class="border-b text-left text-xs uppercase text-muted-foreground">
                                <th class="py-2 pr-3">Ligne</th>
                                <th class="py-2 pr-3">Arrivée</th>
                                <th class="py-2 pr-3">Service</th>
                                <th class="py-2 pr-3">Objet</th>
                                <th class="py-2 pr-3">Type</th>
                                <th class="py-2 pr-3">Division</th>
                                <th class="py-2">Agent</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in preview.preview" :key="row.row" class="border-b">
                                <td class="py-3 pr-3">{{ row.row }}</td>
                                <td class="py-3 pr-3">{{ row.arrival_date }}</td>
                                <td class="py-3 pr-3">{{ row.origin_service }}</td>
                                <td class="max-w-sm py-3 pr-3">{{ row.subject }}</td>
                                <td class="py-3 pr-3">{{ row.type }}</td>
                                <td class="py-3 pr-3">{{ row.division ?? '—' }}</td>
                                <td class="py-3">{{ row.agent ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="disc-mobile-stack -mx-5 mt-5 border-y md:hidden">
                    <MobileDataCard v-for="row in preview.preview" :key="row.row">
                        <template #header>
                            <p class="text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300">Ligne {{ row.row }}</p>
                            <p class="mt-1 font-semibold">{{ row.origin_service }}</p>
                        </template>
                        <template #badge>
                            <span v-if="row.duplicate" class="rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-semibold text-amber-800"
                                >Doublon</span
                            >
                            <span v-else class="rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-semibold text-emerald-700">Valide</span>
                        </template>
                        <p class="line-clamp-3 text-sm">{{ row.subject }}</p>
                        <template #footer>
                            <dl class="disc-mobile-meta">
                                <div>
                                    <dt>Arrivée</dt>
                                    <dd>{{ row.arrival_date }}</dd>
                                </div>
                                <div>
                                    <dt>Type</dt>
                                    <dd>{{ row.type }}</dd>
                                </div>
                                <div>
                                    <dt>Division</dt>
                                    <dd>{{ row.division ?? 'Non définie' }}</dd>
                                </div>
                                <div>
                                    <dt>Agent</dt>
                                    <dd>{{ row.agent ?? 'Non défini' }}</dd>
                                </div>
                            </dl>
                        </template>
                    </MobileDataCard>
                </div>
            </section>
            <section class="rounded-xl border bg-card p-5 md:p-6">
                <h2 class="font-semibold">Historique des imports</h2>
                <div v-if="imports.length" class="mt-4 hidden overflow-x-auto md:block">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b text-left text-xs uppercase text-muted-foreground">
                                <th class="py-2 pr-3">Fichier</th>
                                <th class="py-2 pr-3">Date</th>
                                <th class="py-2 pr-3">Importés</th>
                                <th class="py-2 pr-3">Ignorés</th>
                                <th class="py-2">Par</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in imports" :key="item.id" class="border-b">
                                <td class="py-3 pr-3 font-medium">{{ item.original_name }}</td>
                                <td class="py-3 pr-3">{{ dateTime.format(new Date(item.imported_at)) }}</td>
                                <td class="py-3 pr-3">{{ item.imported_rows }} / {{ item.total_rows }}</td>
                                <td class="py-3 pr-3">{{ item.skipped_rows + item.invalid_rows }}</td>
                                <td class="py-3">{{ item.imported_by.name }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="imports.length" class="disc-mobile-stack -mx-5 mt-4 border-y md:hidden">
                    <MobileDataCard v-for="item in imports" :key="item.id">
                        <template #header>
                            <p class="break-all font-semibold">{{ item.original_name }}</p>
                            <p class="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                                <CalendarDays class="size-3.5" /> {{ dateTime.format(new Date(item.imported_at)) }}
                            </p>
                        </template>
                        <dl class="disc-mobile-meta">
                            <div>
                                <dt>Importés</dt>
                                <dd class="text-emerald-700">{{ item.imported_rows }} / {{ item.total_rows }}</dd>
                            </div>
                            <div>
                                <dt>Ignorés</dt>
                                <dd>{{ item.skipped_rows + item.invalid_rows }}</dd>
                            </div>
                        </dl>
                        <template #footer>
                            <p class="flex items-center gap-2 text-xs text-muted-foreground">
                                <UserRound class="size-3.5" /> Importé par {{ item.imported_by.name }}
                            </p>
                        </template>
                    </MobileDataCard>
                </div>
                <p v-else class="mt-4 text-sm text-muted-foreground">Aucun import effectué.</p>
            </section>
        </div></AppLayout
    >
</template>
