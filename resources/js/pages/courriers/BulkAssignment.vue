<script setup lang="ts">
import AlertBadge from '@/components/disc/AlertBadge.vue';
import MobileDataCard from '@/components/disc/MobileDataCard.vue';
import StatusBadge from '@/components/disc/StatusBadge.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { SharedData } from '@/types';
import type { Agent, Courrier, Division } from '@/types/disc';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Building2, CalendarDays, CheckCheck, RotateCcw, Search, Send, UserRound, UsersRound } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps<{
    courriers: Courrier[];
    filters: { search?: string; scope?: string; division?: number };
    divisions: Division[];
    agents: Agent[];
    lockedDivisionId: number | null;
}>();

const page = usePage<SharedData>();
const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'Africa/Dakar' }).format(new Date());
const filterForm = reactive({
    search: props.filters.search ?? '',
    scope: props.filters.scope ?? 'unassigned',
    division: props.filters.division?.toString() ?? '',
});
const assignmentForm = useForm({
    courrier_ids: [] as number[],
    division_id: props.lockedDivisionId?.toString() ?? '',
    division_assigned_at: today,
    assigned_agent_id: '',
    agent_assigned_at: today,
    instructions: '',
    comment: '',
});
const undoForm = useForm({ token: page.props.flash.bulk_undo?.token ?? '' });
const confirmationOpen = ref(false);

const filteredAgents = computed(() => props.agents.filter((agent) => agent.division_id === Number(assignmentForm.division_id)));
const allSelected = computed(() => props.courriers.length > 0 && assignmentForm.courrier_ids.length === props.courriers.length);
const selectedCount = computed(() => assignmentForm.courrier_ids.length);
const selectedDivision = computed(() => props.divisions.find((division) => division.id === Number(assignmentForm.division_id)));
const selectedAgent = computed(() => props.agents.find((agent) => agent.id === Number(assignmentForm.assigned_agent_id)));
const selectedCourriers = computed(() => props.courriers.filter((courrier) => assignmentForm.courrier_ids.includes(courrier.id)));

watch(
    () => assignmentForm.division_id,
    () => {
        if (!filteredAgents.value.some((agent) => agent.id === Number(assignmentForm.assigned_agent_id))) {
            assignmentForm.assigned_agent_id = '';
        }
    },
);

const applyFilters = () => router.get('/courriers/bulk-assignment', filterForm, { preserveState: true, replace: true });
const toggle = (id: number) => {
    assignmentForm.courrier_ids = assignmentForm.courrier_ids.includes(id)
        ? assignmentForm.courrier_ids.filter((courrierId) => courrierId !== id)
        : [...assignmentForm.courrier_ids, id];
};
const toggleAll = () => {
    assignmentForm.courrier_ids = allSelected.value ? [] : props.courriers.map((courrier) => courrier.id);
};
const requestConfirmation = () => {
    assignmentForm.clearErrors();
    if (!selectedCount.value) {
        assignmentForm.setError('courrier_ids', 'Sélectionnez au moins un courrier.');
        return;
    }
    if (!assignmentForm.division_id) {
        assignmentForm.setError('division_id', 'Sélectionnez la division destinataire.');
        return;
    }
    confirmationOpen.value = true;
};
const confirmAssignment = () => {
    confirmationOpen.value = false;
    assignmentForm.put('/courriers/bulk-assignment', {
        preserveScroll: true,
        onSuccess: () => {
            assignmentForm.courrier_ids = [];
        },
    });
};
const undoAssignment = () => {
    undoForm.token = page.props.flash.bulk_undo?.token ?? '';
    undoForm.post('/courriers/bulk-assignment/undo', { preserveScroll: true });
};
const formatDate = (value: string) =>
    new Intl.DateTimeFormat('fr-SN', { dateStyle: 'medium', timeZone: 'Africa/Dakar' }).format(new Date(`${value}T00:00:00`));
const breadcrumbs = [
    { title: 'Courriers', href: '/courriers' },
    { title: 'Imputation en masse', href: '/courriers/bulk-assignment' },
];
</script>

<template>
    <Head title="Imputation en masse" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-5 p-4 md:p-6 lg:p-8">
            <div
                v-if="page.props.flash.success"
                class="flex flex-col gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <p class="font-semibold">{{ page.props.flash.success }}</p>
                    <p v-if="page.props.flash.bulk_undo" class="mt-0.5 text-xs opacity-80">
                        Vous disposez de 5 minutes pour revenir à l’imputation précédente.
                    </p>
                </div>
                <Button
                    v-if="page.props.flash.bulk_undo"
                    size="sm"
                    variant="outline"
                    class="w-full border-emerald-300 bg-white/70 dark:bg-transparent sm:w-auto"
                    :disabled="undoForm.processing"
                    @click="undoAssignment"
                >
                    <RotateCcw class="mr-2 size-4" /> Annuler l’imputation
                </Button>
            </div>
            <div
                v-if="page.props.flash.error || undoForm.errors.token"
                class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200"
            >
                {{ page.props.flash.error ?? undoForm.errors.token }}
            </div>

            <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
                <div class="flex items-start gap-3">
                    <Button variant="outline" size="icon" as-child>
                        <Link href="/courriers"><ArrowLeft class="size-4" /></Link>
                    </Button>
                    <div>
                        <p class="disc-eyebrow">Traitement groupé</p>
                        <h1 class="mt-1 text-2xl font-semibold tracking-tight">Imputation en masse</h1>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Sélectionnez les courriers puis appliquez une même imputation en une seule opération.
                        </p>
                    </div>
                </div>
                <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-900 dark:bg-amber-950/30">
                    <p class="text-xs font-semibold uppercase tracking-wider text-amber-700 dark:text-amber-300">Sélection</p>
                    <p class="mt-1 text-2xl font-semibold">{{ selectedCount }} <span class="text-sm font-normal">courrier(s)</span></p>
                </div>
            </div>

            <form class="grid gap-3 rounded-[22px] border bg-card/80 p-4 shadow-sm lg:grid-cols-[2fr_1fr_1fr_auto]" @submit.prevent="applyFilters">
                <div class="relative">
                    <Search class="absolute left-3 top-3 size-4 text-muted-foreground" />
                    <Input v-model="filterForm.search" class="pl-9" placeholder="Référence, objet, service..." />
                </div>
                <select v-model="filterForm.scope" class="h-10 rounded-md border bg-background px-3 text-sm">
                    <option value="unassigned">Sans division</option>
                    <option value="division_only">Division sans agent</option>
                    <option value="assigned">Déjà imputés</option>
                    <option value="all">Tous les courriers imputables</option>
                </select>
                <select v-model="filterForm.division" class="h-10 rounded-md border bg-background px-3 text-sm">
                    <option value="">Toutes les divisions</option>
                    <option v-for="division in divisions" :key="division.id" :value="division.id">{{ division.code }}</option>
                </select>
                <Button type="submit" variant="outline">Filtrer</Button>
            </form>

            <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1.55fr)_minmax(340px,.65fr)]">
                <section class="overflow-hidden rounded-[24px] border bg-card/80 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b px-4 py-4">
                        <div>
                            <h2 class="font-semibold">Courriers disponibles</h2>
                            <p class="text-xs text-muted-foreground">{{ courriers.length }} résultat(s), limité(s) à 250 par opération.</p>
                        </div>
                        <Button type="button" size="sm" variant="outline" :disabled="!courriers.length" @click="toggleAll">
                            <CheckCheck class="mr-2 size-4" /> {{ allSelected ? 'Tout désélectionner' : 'Tout sélectionner' }}
                        </Button>
                    </div>

                    <InputError class="px-4 pt-3" :message="assignmentForm.errors.courrier_ids" />
                    <div class="hidden max-h-[620px] overflow-auto md:block">
                        <table class="w-full min-w-[780px] text-left text-sm">
                            <thead class="sticky top-0 z-10 border-b bg-card text-xs uppercase text-muted-foreground">
                                <tr>
                                    <th class="w-12 px-4 py-3">
                                        <input type="checkbox" :checked="allSelected" aria-label="Tout sélectionner" @change="toggleAll" />
                                    </th>
                                    <th class="px-3 py-3">N° DISC</th>
                                    <th class="px-3 py-3">Courrier</th>
                                    <th class="px-3 py-3">Imputation actuelle</th>
                                    <th class="px-3 py-3">État</th>
                                    <th class="px-3 py-3">Alerte</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr
                                    v-for="courrier in courriers"
                                    :key="courrier.id"
                                    class="cursor-pointer transition-colors hover:bg-muted/40"
                                    :class="assignmentForm.courrier_ids.includes(courrier.id) ? 'bg-amber-50/70 dark:bg-amber-950/20' : ''"
                                    @click="toggle(courrier.id)"
                                >
                                    <td class="px-4 py-3" @click.stop>
                                        <input
                                            type="checkbox"
                                            :checked="assignmentForm.courrier_ids.includes(courrier.id)"
                                            :aria-label="`Sélectionner ${courrier.registration_number}`"
                                            @change="toggle(courrier.id)"
                                        />
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-3 font-semibold text-amber-700 dark:text-amber-300">
                                        {{ courrier.registration_number }}
                                        <p class="mt-1 text-xs font-normal text-muted-foreground">{{ formatDate(courrier.arrival_date) }}</p>
                                    </td>
                                    <td class="max-w-sm px-3 py-3">
                                        <p class="line-clamp-2 font-medium">{{ courrier.subject }}</p>
                                        <p class="mt-1 truncate text-xs text-muted-foreground">{{ courrier.origin_service }}</p>
                                    </td>
                                    <td class="px-3 py-3">
                                        <p>{{ courrier.division?.code ?? 'Aucune division' }}</p>
                                        <p class="text-xs text-muted-foreground">{{ courrier.assigned_agent?.name ?? 'Aucun agent' }}</p>
                                    </td>
                                    <td class="px-3 py-3"><StatusBadge :status="courrier.status" :label="courrier.status_label" /></td>
                                    <td class="px-3 py-3"><AlertBadge :level="courrier.metrics.current_alert" /></td>
                                </tr>
                                <tr v-if="!courriers.length">
                                    <td colspan="6" class="px-4 py-14 text-center text-muted-foreground">
                                        Aucun courrier imputable ne correspond aux filtres.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="disc-mobile-stack max-h-[620px] overflow-y-auto md:hidden">
                        <MobileDataCard
                            v-for="courrier in courriers"
                            :key="courrier.id"
                            interactive
                            :selected="assignmentForm.courrier_ids.includes(courrier.id)"
                            role="checkbox"
                            :aria-checked="assignmentForm.courrier_ids.includes(courrier.id)"
                            tabindex="0"
                            @click="toggle(courrier.id)"
                            @keydown.space.prevent="toggle(courrier.id)"
                            @keydown.enter.prevent="toggle(courrier.id)"
                        >
                            <template #header>
                                <div class="flex items-start gap-3">
                                    <input
                                        type="checkbox"
                                        class="mt-1 size-5 shrink-0 accent-amber-500"
                                        :checked="assignmentForm.courrier_ids.includes(courrier.id)"
                                        :aria-label="`Sélectionner ${courrier.registration_number}`"
                                        @click.stop
                                        @change="toggle(courrier.id)"
                                    />
                                    <div class="min-w-0">
                                        <p class="font-semibold text-amber-700 dark:text-amber-300">{{ courrier.registration_number }}</p>
                                        <p class="mt-0.5 text-xs text-muted-foreground">{{ formatDate(courrier.arrival_date) }}</p>
                                    </div>
                                </div>
                            </template>
                            <template #badge><AlertBadge :level="courrier.metrics.current_alert" /></template>

                            <p class="line-clamp-2 text-sm font-semibold">{{ courrier.subject }}</p>
                            <p class="mt-1 truncate text-xs text-muted-foreground">{{ courrier.origin_service }}</p>

                            <template #footer>
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <StatusBadge :status="courrier.status" :label="courrier.status_label" />
                                    <p class="text-right text-xs text-muted-foreground">
                                        {{ courrier.division?.code ?? 'Aucune division' }} ·
                                        {{ courrier.assigned_agent?.name ?? 'Aucun agent' }}
                                    </p>
                                </div>
                            </template>
                        </MobileDataCard>
                        <div v-if="!courriers.length" class="px-4 py-14 text-center text-sm text-muted-foreground">
                            Aucun courrier imputable ne correspond aux filtres.
                        </div>
                    </div>
                </section>

                <form
                    class="rounded-[24px] border bg-card/90 p-5 shadow-sm backdrop-blur-xl md:p-6 xl:sticky xl:top-5"
                    @submit.prevent="requestConfirmation"
                >
                    <div class="flex items-center gap-3">
                        <span class="grid size-11 place-items-center rounded-2xl bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                            <UsersRound class="size-5" />
                        </span>
                        <div>
                            <h2 class="font-semibold">Nouvelle imputation</h2>
                            <p class="text-xs text-muted-foreground">Appliquée aux {{ selectedCount }} courrier(s) sélectionné(s).</p>
                        </div>
                    </div>

                    <div class="mt-6 space-y-4">
                        <div class="grid gap-2">
                            <Label>Division *</Label>
                            <select
                                v-model="assignmentForm.division_id"
                                required
                                :disabled="lockedDivisionId !== null"
                                class="h-10 rounded-md border bg-background px-3 text-sm disabled:opacity-70"
                            >
                                <option disabled value="">Sélectionner une division</option>
                                <option v-for="division in divisions" :key="division.id" :value="division.id">
                                    {{ division.code }} — {{ division.name }}
                                </option>
                            </select>
                            <p v-if="lockedDivisionId" class="text-xs text-muted-foreground">
                                Votre périmètre est limité à {{ selectedDivision?.code }}.
                            </p>
                            <InputError :message="assignmentForm.errors.division_id" />
                        </div>
                        <div class="grid gap-2">
                            <Label>Date d’imputation à la division *</Label>
                            <Input v-model="assignmentForm.division_assigned_at" type="date" required />
                            <InputError :message="assignmentForm.errors.division_assigned_at" />
                        </div>
                        <div class="grid gap-2">
                            <Label>Agent responsable</Label>
                            <select
                                v-model="assignmentForm.assigned_agent_id"
                                :disabled="!assignmentForm.division_id"
                                class="h-10 rounded-md border bg-background px-3 text-sm disabled:opacity-60"
                            >
                                <option value="">Imputer uniquement à la division</option>
                                <option v-for="agent in filteredAgents" :key="agent.id" :value="agent.id">{{ agent.name }}</option>
                            </select>
                            <InputError :message="assignmentForm.errors.assigned_agent_id" />
                        </div>
                        <div v-if="assignmentForm.assigned_agent_id" class="grid gap-2">
                            <Label>Date d’imputation à l’agent *</Label>
                            <Input v-model="assignmentForm.agent_assigned_at" type="date" required />
                            <InputError :message="assignmentForm.errors.agent_assigned_at" />
                        </div>
                        <div class="grid gap-2">
                            <Label>Instructions communes</Label>
                            <textarea
                                v-model="assignmentForm.instructions"
                                rows="4"
                                class="rounded-md border bg-background px-3 py-2 text-sm"
                                placeholder="Laissez vide pour conserver les instructions existantes"
                            />
                            <InputError :message="assignmentForm.errors.instructions" />
                        </div>
                        <div class="grid gap-2">
                            <Label>Commentaire d’historique</Label>
                            <Input v-model="assignmentForm.comment" placeholder="Motif ou précision facultative" />
                            <InputError :message="assignmentForm.errors.comment" />
                        </div>
                    </div>

                    <Button class="mt-6 w-full" size="lg" :disabled="assignmentForm.processing || !selectedCount || !assignmentForm.division_id">
                        <Send class="mr-2 size-4" /> Imputer {{ selectedCount || '' }} courrier(s)
                    </Button>
                    <p class="mt-3 text-center text-xs text-muted-foreground">Une trace individuelle sera ajoutée à chaque historique.</p>
                </form>
            </div>

            <Dialog v-model:open="confirmationOpen">
                <DialogContent class="max-h-[90svh] overflow-y-auto rounded-[24px] sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>Confirmer l’imputation en masse</DialogTitle>
                        <DialogDescription>Vérifiez le résumé avant de modifier les courriers sélectionnés.</DialogDescription>
                    </DialogHeader>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="rounded-2xl bg-muted/60 p-4">
                            <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                <CheckCheck class="size-4" /> Sélection
                            </p>
                            <p class="mt-2 text-2xl font-semibold">{{ selectedCount }} <span class="text-sm font-normal">courrier(s)</span></p>
                        </div>
                        <div class="rounded-2xl bg-muted/60 p-4">
                            <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                <Building2 class="size-4" /> Destination
                            </p>
                            <p class="mt-2 font-semibold">{{ selectedDivision?.code }} — {{ selectedDivision?.name }}</p>
                        </div>
                        <div class="rounded-2xl bg-muted/60 p-4">
                            <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                <UserRound class="size-4" /> Responsable
                            </p>
                            <p class="mt-2 font-semibold">{{ selectedAgent?.name ?? 'Division uniquement' }}</p>
                        </div>
                        <div class="rounded-2xl bg-muted/60 p-4">
                            <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                <CalendarDays class="size-4" /> Date d’imputation
                            </p>
                            <p class="mt-2 font-semibold">{{ formatDate(assignmentForm.division_assigned_at) }}</p>
                        </div>
                    </div>

                    <div class="rounded-2xl border p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Courriers concernés</p>
                        <ul class="mt-3 space-y-2 text-sm">
                            <li v-for="courrier in selectedCourriers.slice(0, 5)" :key="courrier.id" class="flex items-start justify-between gap-3">
                                <span class="font-semibold text-amber-700 dark:text-amber-300">{{ courrier.registration_number }}</span>
                                <span class="line-clamp-1 min-w-0 flex-1 text-right text-muted-foreground">{{ courrier.subject }}</span>
                            </li>
                        </ul>
                        <p v-if="selectedCourriers.length > 5" class="mt-3 text-xs text-muted-foreground">
                            Et {{ selectedCourriers.length - 5 }} autre(s) courrier(s).
                        </p>
                    </div>

                    <div
                        v-if="assignmentForm.instructions || assignmentForm.comment"
                        class="rounded-2xl border border-amber-200 bg-amber-50/60 p-4 text-sm dark:border-amber-900 dark:bg-amber-950/20"
                    >
                        <p v-if="assignmentForm.instructions"><strong>Instructions :</strong> {{ assignmentForm.instructions }}</p>
                        <p v-if="assignmentForm.comment" :class="assignmentForm.instructions ? 'mt-2' : ''">
                            <strong>Commentaire :</strong> {{ assignmentForm.comment }}
                        </p>
                    </div>

                    <p class="text-xs leading-5 text-muted-foreground">
                        Après confirmation, un bouton d’annulation restera disponible pendant 5 minutes, tant qu’aucun courrier concerné n’a été
                        modifié.
                    </p>

                    <DialogFooter class="gap-2 sm:gap-0">
                        <Button type="button" variant="outline" @click="confirmationOpen = false">Revenir et modifier</Button>
                        <Button type="button" :disabled="assignmentForm.processing" @click="confirmAssignment"
                            ><Send class="mr-2 size-4" /> Confirmer l’imputation</Button
                        >
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
