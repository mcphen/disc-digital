<script setup lang="ts">
import MobileDataCard from '@/components/disc/MobileDataCard.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { SharedData } from '@/types';
import type { CourrierType, Division } from '@/types/disc';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Plus, Save } from 'lucide-vue-next';
import { reactive } from 'vue';

const props = defineProps<{ thresholds: { green_max: number; yellow_max: number }; divisions: Division[]; types: CourrierType[] }>();
const page = usePage<SharedData>();
const thresholdsForm = useForm({ ...props.thresholds });
const divisionForm = useForm({ code: '', name: '', is_active: true });
const typeForm = useForm({ code: '', name: '', requires_response: true, agent_delay_days: 5, director_delay_days: 2, is_active: true });
const editableDivisions = reactive(props.divisions.map((division) => ({ ...division, is_active: division.is_active ?? true })));
const editableTypes = reactive(
    props.types.map((type) => ({
        ...type,
        agent_delay_days: type.agent_delay_days ?? 0,
        director_delay_days: type.director_delay_days ?? 0,
        is_active: type.is_active ?? true,
    })),
);

const saveThresholds = () => thresholdsForm.put('/administration/settings/alerts', { preserveScroll: true });
const addDivision = () => divisionForm.post('/administration/settings/divisions', { preserveScroll: true, onSuccess: () => divisionForm.reset() });
const saveDivision = (division: Division) =>
    router.put(`/administration/settings/divisions/${division.id}`, { ...division }, { preserveScroll: true });
const addType = () => typeForm.post('/administration/settings/types', { preserveScroll: true, onSuccess: () => typeForm.reset() });
const saveType = (type: CourrierType) => router.put(`/administration/settings/types/${type.id}`, { ...type }, { preserveScroll: true });
const breadcrumbs = [
    { title: 'Administration', href: '/administration/settings' },
    { title: 'Paramètres DISC', href: '/administration/settings' },
];
</script>

<template>
    <Head title="Paramètres DISC" />
    <AppLayout :breadcrumbs="breadcrumbs"
        ><div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold">Paramètres DISC</h1>
                <p class="text-sm text-muted-foreground">
                    Ces valeurs pilotent les alertes et les nouveaux courriers. Les délais historiques restent inchangés.
                </p>
            </div>
            <div v-if="page.props.flash.success" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ page.props.flash.success }}
            </div>
            <section class="rounded-xl border bg-card p-5 md:p-6">
                <h2 class="font-semibold">Seuils d’alerte</h2>
                <p class="mt-1 text-sm text-muted-foreground">Pourcentage du délai consommé avant changement de couleur.</p>
                <form class="mt-5 grid max-w-2xl gap-4 sm:grid-cols-3 sm:items-end" @submit.prevent="saveThresholds">
                    <div class="grid gap-2">
                        <Label>Vert jusqu’à (%)</Label><Input v-model="thresholdsForm.green_max" type="number" min="1" max="99" /><InputError
                            :message="thresholdsForm.errors.green_max"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>Jaune jusqu’à (%)</Label><Input v-model="thresholdsForm.yellow_max" type="number" min="2" max="100" /><InputError
                            :message="thresholdsForm.errors.yellow_max"
                        />
                    </div>
                    <Button :disabled="thresholdsForm.processing"><Save class="mr-2 size-4" /> Enregistrer</Button>
                </form>
            </section>
            <section class="rounded-xl border bg-card p-5 md:p-6">
                <h2 class="font-semibold">Divisions</h2>
                <div class="mt-5 hidden overflow-x-auto md:block">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b text-left text-xs uppercase text-muted-foreground">
                                <th class="py-2 pr-3">Code</th>
                                <th class="py-2 pr-3">Libellé</th>
                                <th class="py-2 pr-3">Active</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="division in editableDivisions" :key="division.id" class="border-b">
                                <td class="py-3 pr-3"><Input v-model="division.code" /></td>
                                <td class="py-3 pr-3"><Input v-model="division.name" /></td>
                                <td class="py-3 pr-3"><input v-model="division.is_active" type="checkbox" /></td>
                                <td class="py-3 text-right">
                                    <Button size="sm" variant="outline" @click="saveDivision(division)">Enregistrer</Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="disc-mobile-stack -mx-5 mt-5 border-y md:hidden">
                    <MobileDataCard v-for="division in editableDivisions" :key="division.id">
                        <template #header>
                            <p class="font-semibold">{{ division.code || 'Division sans code' }}</p>
                            <p class="mt-0.5 text-xs text-muted-foreground">Modifier cette division</p>
                        </template>
                        <template #badge>
                            <span
                                class="rounded-full px-2.5 py-1 text-[11px] font-semibold"
                                :class="division.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-muted text-muted-foreground'"
                            >
                                {{ division.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </template>
                        <div class="grid gap-3">
                            <div class="grid gap-1.5"><Label>Code</Label><Input v-model="division.code" /></div>
                            <div class="grid gap-1.5"><Label>Libellé</Label><Input v-model="division.name" /></div>
                            <label class="flex min-h-11 items-center justify-between rounded-xl border bg-background/60 px-3 text-sm">
                                Division active
                                <input v-model="division.is_active" type="checkbox" class="size-5 accent-amber-500" />
                            </label>
                        </div>
                        <template #footer
                            ><Button class="w-full" variant="outline" @click="saveDivision(division)"
                                ><Save class="mr-2 size-4" /> Enregistrer</Button
                            ></template
                        >
                    </MobileDataCard>
                </div>
                <form class="mt-5 grid gap-3 rounded-lg bg-muted/50 p-4 sm:grid-cols-[150px_1fr_auto_auto]" @submit.prevent="addDivision">
                    <Input v-model="divisionForm.code" required placeholder="Code" /><Input
                        v-model="divisionForm.name"
                        required
                        placeholder="Libellé de la division"
                    /><label class="flex items-center gap-2 text-sm"><input v-model="divisionForm.is_active" type="checkbox" /> Active</label
                    ><Button :disabled="divisionForm.processing"><Plus class="mr-2 size-4" /> Ajouter</Button>
                </form>
            </section>
            <section class="rounded-xl border bg-card p-5 md:p-6">
                <h2 class="font-semibold">Types et délais</h2>
                <p class="mt-1 text-sm text-muted-foreground">Le type Avis peut être activé ici après validation de sa répartition.</p>
                <div class="mt-5 hidden overflow-x-auto lg:block">
                    <table class="w-full min-w-[900px] text-sm">
                        <thead>
                            <tr class="border-b text-left text-xs uppercase text-muted-foreground">
                                <th class="py-2 pr-3">Code</th>
                                <th class="py-2 pr-3">Libellé</th>
                                <th class="py-2 pr-3">Réponse</th>
                                <th class="py-2 pr-3">Agent</th>
                                <th class="py-2 pr-3">Directeur</th>
                                <th class="py-2 pr-3">Actif</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="type in editableTypes" :key="type.id" class="border-b">
                                <td class="py-3 pr-3"><Input v-model="type.code" /></td>
                                <td class="py-3 pr-3"><Input v-model="type.name" /></td>
                                <td class="py-3 pr-3"><input v-model="type.requires_response" type="checkbox" /></td>
                                <td class="py-3 pr-3">
                                    <Input v-model="type.agent_delay_days" type="number" min="1" :disabled="!type.requires_response" />
                                </td>
                                <td class="py-3 pr-3">
                                    <Input v-model="type.director_delay_days" type="number" min="1" :disabled="!type.requires_response" />
                                </td>
                                <td class="py-3 pr-3"><input v-model="type.is_active" type="checkbox" /></td>
                                <td class="py-3 text-right"><Button size="sm" variant="outline" @click="saveType(type)">Enregistrer</Button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="disc-mobile-stack -mx-5 mt-5 border-y lg:hidden">
                    <MobileDataCard v-for="type in editableTypes" :key="type.id">
                        <template #header>
                            <p class="font-semibold">{{ type.name || 'Type sans libellé' }}</p>
                            <p class="mt-0.5 text-xs text-muted-foreground">Code {{ type.code || '—' }}</p>
                        </template>
                        <template #badge>
                            <span
                                class="rounded-full px-2.5 py-1 text-[11px] font-semibold"
                                :class="type.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-muted text-muted-foreground'"
                            >
                                {{ type.is_active ? 'Actif' : 'Inactif' }}
                            </span>
                        </template>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="grid gap-1.5"><Label>Code</Label><Input v-model="type.code" /></div>
                            <div class="grid gap-1.5"><Label>Libellé</Label><Input v-model="type.name" /></div>
                            <div class="grid gap-1.5">
                                <Label>Délai agent (jours)</Label>
                                <Input v-model="type.agent_delay_days" type="number" min="1" :disabled="!type.requires_response" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Délai directeur (jours)</Label>
                                <Input v-model="type.director_delay_days" type="number" min="1" :disabled="!type.requires_response" />
                            </div>
                            <label class="flex min-h-11 items-center justify-between rounded-xl border bg-background/60 px-3 text-sm">
                                Réponse requise
                                <input v-model="type.requires_response" type="checkbox" class="size-5 accent-amber-500" />
                            </label>
                            <label class="flex min-h-11 items-center justify-between rounded-xl border bg-background/60 px-3 text-sm">
                                Type actif
                                <input v-model="type.is_active" type="checkbox" class="size-5 accent-amber-500" />
                            </label>
                        </div>
                        <template #footer
                            ><Button class="w-full" variant="outline" @click="saveType(type)"
                                ><Save class="mr-2 size-4" /> Enregistrer</Button
                            ></template
                        >
                    </MobileDataCard>
                </div>
                <form class="mt-5 grid gap-3 rounded-lg bg-muted/50 p-4 md:grid-cols-7" @submit.prevent="addType">
                    <Input v-model="typeForm.code" required placeholder="Code" /><Input
                        v-model="typeForm.name"
                        required
                        placeholder="Libellé"
                        class="md:col-span-2"
                    /><label class="flex items-center gap-2 text-sm"><input v-model="typeForm.requires_response" type="checkbox" /> Réponse</label
                    ><Input
                        v-model="typeForm.agent_delay_days"
                        type="number"
                        min="1"
                        placeholder="J. agent"
                        :disabled="!typeForm.requires_response"
                    /><Input
                        v-model="typeForm.director_delay_days"
                        type="number"
                        min="1"
                        placeholder="J. Directeur"
                        :disabled="!typeForm.requires_response"
                    /><Button :disabled="typeForm.processing"><Plus class="mr-2 size-4" /> Ajouter</Button>
                </form>
            </section>
        </div></AppLayout
    >
</template>
