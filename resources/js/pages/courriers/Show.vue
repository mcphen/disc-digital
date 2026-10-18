<script setup lang="ts">
import AlertBadge from '@/components/disc/AlertBadge.vue';
import ChoiceChips from '@/components/disc/ChoiceChips.vue';
import StatusBadge from '@/components/disc/StatusBadge.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { SharedData } from '@/types';
import type { Agent, Courrier, Division } from '@/types/disc';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Clock3,
    Download,
    Paperclip,
    RotateCcw,
    Send,
    ShieldCheck,
    Trash2,
    Upload,
    UserRoundCheck,
} from 'lucide-vue-next';
import { computed, watch } from 'vue';

const props = defineProps<{
    courrier: Courrier;
    divisions: Division[];
    agents: Agent[];
    backUrl: string;
    adjacent: {
        previous: { id: number; label: string } | null;
        next: { id: number; label: string } | null;
    };
    permissions: { assign: boolean; submit: boolean; review: boolean; sign: boolean; attach: boolean };
}>();

const page = usePage<SharedData>();
const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'Africa/Dakar' }).format(new Date());
const assignmentForm = useForm({
    division_id: props.courrier.division?.id.toString() ?? '',
    division_assigned_at: props.courrier.division_assigned_at ?? today,
    assigned_agent_id: props.courrier.assigned_agent?.id.toString() ?? '',
    agent_assigned_at: props.courrier.agent_assigned_at ?? '',
    contributing_division_ids: props.courrier.contributing_divisions.map((division) => division.id),
    co_agent_ids: props.courrier.co_agents.map((agent) => agent.id),
    instructions: props.courrier.instructions ?? '',
    comment: '',
});
const submitForm = useForm({ submitted_at: props.courrier.submitted_at ?? today, comment: '' });
const reviewForm = useForm({ decision: 'approve', reviewed_at: props.courrier.division_validated_at ?? today, comment: '' });
const signForm = useForm({ signed_at: props.courrier.signed_at ?? today, comment: '' });
const attachmentForm = useForm<{ file: File | null }>({ file: null });
const filteredAgents = computed(() => props.agents.filter((agent) => !agent.division_id || agent.division_id === Number(assignmentForm.division_id)));
// Divisions contributrices : toutes sauf la pilote. Co-agents : agents des divisions imputées, sauf le responsable.
const contributingOptions = computed(() =>
    props.divisions
        .filter((division) => division.id !== Number(assignmentForm.division_id))
        .map((division) => ({ id: division.id, label: division.code, hint: division.name })),
);
const coAgentOptions = computed(() => {
    const involved = [Number(assignmentForm.division_id), ...assignmentForm.contributing_division_ids];
    const codes = new Map(props.divisions.map((division) => [division.id, division.code]));

    return props.agents
        .filter((agent) => agent.id !== Number(assignmentForm.assigned_agent_id))
        .filter((agent) => !agent.division_id || involved.includes(agent.division_id))
        .map((agent) => ({ id: agent.id, label: agent.name, hint: agent.division_id ? codes.get(agent.division_id) : undefined }));
});
// Garder la sélection cohérente quand la division pilote, les contributions ou l'agent responsable changent.
// Chaque source est comparée séparément, et les listes ne sont réécrites que si elles changent :
// sans cela, réécrire une liste surveillée relancerait le watcher indéfiniment.
watch([() => assignmentForm.division_id, () => assignmentForm.assigned_agent_id, () => assignmentForm.contributing_division_ids.join(',')], () => {
    const contributions = assignmentForm.contributing_division_ids.filter((id) => id !== Number(assignmentForm.division_id));
    if (contributions.length !== assignmentForm.contributing_division_ids.length) {
        assignmentForm.contributing_division_ids = contributions;
    }

    const allowed = new Set(coAgentOptions.value.map((option) => option.id));
    const coAgents = assignmentForm.assigned_agent_id ? assignmentForm.co_agent_ids.filter((id) => allowed.has(id)) : [];
    if (coAgents.length !== assignmentForm.co_agent_ids.length) {
        assignmentForm.co_agent_ids = coAgents;
    }
});

const saveAssignment = () => assignmentForm.put(`/courriers/${props.courrier.id}/assignment`, { preserveScroll: true });
const submitToDivision = () => submitForm.put(`/courriers/${props.courrier.id}/submission`, { preserveScroll: true });
const review = (decision: 'approve' | 'reject') => {
    reviewForm.decision = decision;
    reviewForm.put(`/courriers/${props.courrier.id}/review`, { preserveScroll: true });
};
const sign = () => signForm.put(`/courriers/${props.courrier.id}/signature`, { preserveScroll: true });
const selectAttachment = (event: Event) => {
    attachmentForm.file = (event.target as HTMLInputElement).files?.[0] ?? null;
};
const uploadAttachment = () =>
    attachmentForm.post(`/courriers/${props.courrier.id}/attachments`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => attachmentForm.reset(),
    });
const deleteAttachment = (id: number) => {
    if (window.confirm('Supprimer définitivement cette pièce jointe ?')) {
        router.delete(`/attachments/${id}`, { preserveScroll: true });
    }
};

const breadcrumbs = [
    { title: 'Courriers', href: props.backUrl },
    { title: props.courrier.registration_number, href: `/courriers/${props.courrier.id}` },
];
const dateFormat = new Intl.DateTimeFormat('fr-SN', { dateStyle: 'long', timeZone: 'Africa/Dakar' });
const dateTimeFormat = new Intl.DateTimeFormat('fr-SN', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Africa/Dakar' });
const formatDate = (value: string | null) => (value ? dateFormat.format(new Date(`${value}T00:00:00`)) : '—');
const formatDateTime = (value: string) => dateTimeFormat.format(new Date(value));
const formatSize = (bytes: number) => (bytes < 1024 * 1024 ? `${Math.ceil(bytes / 1024)} Ko` : `${(bytes / 1024 / 1024).toFixed(1)} Mo`);
const eventLabels: Record<string, string> = {
    created: 'Courrier enregistré',
    assigned: 'Affectation mise à jour',
    bulk_assigned: 'Affectation en masse',
    bulk_assignment_undone: 'Affectation en masse annulée',
    submitted: 'Dossier soumis au Directeur',
    submitted_for_review: 'Projet soumis au Chef de Division',
    division_approved: 'Projet validé par le Chef de Division',
    division_rejected: 'Correction demandée par le Chef de Division',
    signed: 'Réponse signée',
};
const eventLabel = (event: string) => eventLabels[event] ?? event;
const adjacentUrl = (id: number) => `/courriers/${id}?return=${encodeURIComponent(props.backUrl)}`;
</script>

<template>
    <Head :title="`Courrier ${courrier.registration_number}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-5 p-4 md:p-6">
            <div
                v-if="page.props.flash.success"
                class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200"
            >
                {{ page.props.flash.success }}
            </div>
            <div class="flex flex-col justify-between gap-3 md:flex-row md:items-start">
                <div class="flex min-w-0 gap-3">
                    <Button variant="outline" size="icon" as-child
                        ><Link :href="backUrl"><ArrowLeft class="size-4" /></Link
                    ></Button>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="break-words text-xl font-semibold sm:text-2xl">Courrier {{ courrier.registration_number }}</h1>
                            <StatusBadge :status="courrier.status" :label="courrier.status_label" />
                        </div>
                        <p class="mt-1 text-sm text-muted-foreground">Reçu le {{ formatDate(courrier.arrival_date) }} · {{ courrier.type.name }}</p>
                    </div>
                </div>
                <div class="grid grid-cols-2 items-center gap-2 sm:flex sm:flex-wrap">
                    <Button v-if="adjacent.previous" size="sm" variant="outline" class="w-full sm:w-auto" as-child>
                        <Link :href="adjacentUrl(adjacent.previous.id)" :title="`Courrier ${adjacent.previous.label}`">
                            <ChevronLeft class="mr-1 size-4" /> Précédent
                        </Link>
                    </Button>
                    <Button v-if="adjacent.next" size="sm" variant="outline" class="w-full sm:w-auto" as-child>
                        <Link :href="adjacentUrl(adjacent.next.id)" :title="`Courrier ${adjacent.next.label}`">
                            Suivant <ChevronRight class="ml-1 size-4" />
                        </Link>
                    </Button>
                    <div class="col-span-2 sm:col-auto"><AlertBadge :level="courrier.metrics.current_alert" /></div>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border bg-card p-4">
                    <p class="text-xs font-medium uppercase text-muted-foreground">Échéance globale</p>
                    <p class="mt-2 font-semibold">{{ formatDate(courrier.metrics.due_date) }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ (courrier.agent_delay_days ?? 0) + (courrier.director_delay_days ?? 0) }} jours calendaires
                    </p>
                </div>
                <div class="rounded-xl border bg-card p-4">
                    <p class="text-xs font-medium uppercase text-muted-foreground">Délai agent</p>
                    <div class="mt-2 flex items-center justify-between">
                        <p class="font-semibold">{{ courrier.agent_delay_days ?? '—' }} jours</p>
                        <AlertBadge :level="courrier.metrics.agent_alert" />
                    </div>
                    <p class="mt-1 text-xs text-muted-foreground">Durée réalisée : {{ courrier.metrics.agent_duration_days ?? '—' }} jour(s)</p>
                </div>
                <div class="rounded-xl border bg-card p-4">
                    <p class="text-xs font-medium uppercase text-muted-foreground">Délai Directeur</p>
                    <div class="mt-2 flex items-center justify-between">
                        <p class="font-semibold">{{ courrier.director_delay_days ?? '—' }} jours</p>
                        <AlertBadge :level="courrier.metrics.director_alert" />
                    </div>
                    <p class="mt-1 text-xs text-muted-foreground">Durée réalisée : {{ courrier.metrics.director_duration_days ?? '—' }} jour(s)</p>
                </div>
                <div class="rounded-xl border bg-card p-4">
                    <p class="text-xs font-medium uppercase text-muted-foreground">Résultat</p>
                    <p class="mt-2 font-semibold" :class="courrier.metrics.observation === 'Hors délais' ? 'text-red-600' : 'text-emerald-600'">
                        {{ courrier.metrics.observation ?? 'Traitement en cours' }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">Écart : {{ courrier.metrics.global_gap_days ?? '—' }} jour(s)</p>
                </div>
            </div>

            <div class="grid gap-5 xl:grid-cols-3">
                <div class="space-y-5 xl:col-span-2">
                    <section class="rounded-xl border bg-card p-5 md:p-6">
                        <h2 class="font-semibold">Informations du courrier</h2>
                        <dl class="mt-5 grid gap-x-6 gap-y-5 md:grid-cols-2">
                            <div>
                                <dt class="text-xs uppercase text-muted-foreground">Référence</dt>
                                <dd class="mt-1 text-sm">{{ courrier.reference ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs uppercase text-muted-foreground">N° arrivée</dt>
                                <dd class="mt-1 text-sm">{{ courrier.arrival_number ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs uppercase text-muted-foreground">Service d'origine</dt>
                                <dd class="mt-1 text-sm">{{ courrier.origin_service }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs uppercase text-muted-foreground">
                                    {{ courrier.contributing_divisions.length ? 'Division pilote' : 'Division' }}
                                </dt>
                                <dd class="mt-1 text-sm">
                                    {{ courrier.division ? `${courrier.division.code} — ${courrier.division.name}` : 'Non affectée' }}
                                </dd>
                                <dd v-if="courrier.contributing_divisions.length" class="mt-1 text-xs text-muted-foreground">
                                    En contribution :
                                    {{ courrier.contributing_divisions.map((division) => division.code).join(', ') }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs uppercase text-muted-foreground">Agent responsable</dt>
                                <dd class="mt-1 text-sm">{{ courrier.assigned_agent?.name ?? courrier.legacy_agent_name ?? 'Non affecté' }}</dd>
                                <dd v-if="courrier.co_agents.length" class="mt-1 text-xs text-muted-foreground">
                                    Co-agents : {{ courrier.co_agents.map((agent) => agent.name).join(', ') }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs uppercase text-muted-foreground">Production de la réponse</dt>
                                <dd class="mt-1 text-sm">{{ formatDate(courrier.submitted_at) }}</dd>
                            </div>
                            <div v-if="courrier.division_validated_at">
                                <dt class="text-xs uppercase text-muted-foreground">Validation de la division</dt>
                                <dd class="mt-1 text-sm">
                                    {{ formatDate(courrier.division_validated_at) }}
                                    <span v-if="courrier.division_validated_by"> · {{ courrier.division_validated_by.name }}</span>
                                </dd>
                            </div>
                            <div class="md:col-span-2">
                                <dt class="text-xs uppercase text-muted-foreground">Objet / Diligences</dt>
                                <dd class="mt-1 whitespace-pre-line text-sm">{{ courrier.subject }}</dd>
                            </div>
                            <div class="md:col-span-2">
                                <dt class="text-xs uppercase text-muted-foreground">Instructions DSP</dt>
                                <dd class="mt-1 whitespace-pre-line text-sm">{{ courrier.instructions ?? '—' }}</dd>
                            </div>
                        </dl>
                    </section>

                    <section class="rounded-xl border bg-card p-5 md:p-6">
                        <div class="flex items-center gap-3">
                            <div class="rounded-lg bg-sky-100 p-2 text-sky-700 dark:bg-sky-950 dark:text-sky-300">
                                <Paperclip class="size-5" />
                            </div>
                            <div>
                                <h2 class="font-semibold">Pièces jointes</h2>
                                <p class="text-sm text-muted-foreground">Documents privés associés au courrier, limités à 10 Mo.</p>
                            </div>
                        </div>

                        <form v-if="permissions.attach" class="mt-5 flex flex-col gap-3 sm:flex-row" @submit.prevent="uploadAttachment">
                            <input
                                type="file"
                                required
                                accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                                class="block flex-1 rounded-md border bg-background p-2 text-sm"
                                @change="selectAttachment"
                            />
                            <Button :disabled="attachmentForm.processing || !attachmentForm.file"> <Upload class="mr-2 size-4" /> Ajouter </Button>
                        </form>
                        <InputError class="mt-2" :message="attachmentForm.errors.file" />

                        <div v-if="courrier.attachments?.length" class="mt-5 divide-y rounded-lg border">
                            <div v-for="attachment in courrier.attachments" :key="attachment.id" class="flex items-center gap-3 p-3">
                                <Paperclip class="size-4 shrink-0 text-muted-foreground" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium">{{ attachment.original_name }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ formatSize(attachment.size) }} · {{ attachment.uploaded_by?.name ?? 'Utilisateur' }} ·
                                        {{ formatDateTime(attachment.created_at) }}
                                    </p>
                                </div>
                                <Button size="icon" variant="outline" as-child>
                                    <a :href="`/attachments/${attachment.id}/download`" title="Télécharger"><Download class="size-4" /></a>
                                </Button>
                                <Button
                                    v-if="page.props.auth.user.role === 'admin' || attachment.uploaded_by?.id === page.props.auth.user.id"
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    title="Supprimer"
                                    @click="deleteAttachment(attachment.id)"
                                >
                                    <Trash2 class="size-4 text-red-600" />
                                </Button>
                            </div>
                        </div>
                        <p v-else class="mt-5 text-sm text-muted-foreground">Aucune pièce jointe.</p>
                    </section>

                    <section
                        v-if="
                            permissions.assign &&
                            ['registered', 'assigned_division', 'assigned_agent', 'correction_requested'].includes(courrier.status)
                        "
                        class="rounded-xl border bg-card p-5 md:p-6"
                    >
                        <div class="mb-5 flex items-center gap-3">
                            <div class="rounded-lg bg-amber-100 p-2 text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                                <UserRoundCheck class="size-5" />
                            </div>
                            <div>
                                <h2 class="font-semibold">Imputation et affectation</h2>
                                <p class="text-sm text-muted-foreground">
                                    La division pilote et l'agent responsable rédigent la réponse ; les contributeurs suivent le dossier et y
                                    apportent leurs éléments.
                                </p>
                            </div>
                        </div>
                        <form class="grid gap-4 md:grid-cols-2" @submit.prevent="saveAssignment">
                            <div class="grid gap-2">
                                <Label>Division pilote *</Label
                                ><select v-model="assignmentForm.division_id" required class="h-10 rounded-md border bg-background px-3 text-sm">
                                    <option disabled value="">Sélectionner</option>
                                    <option v-for="division in divisions" :key="division.id" :value="division.id">
                                        {{ division.code }} — {{ division.name }}
                                    </option></select
                                ><InputError :message="assignmentForm.errors.division_id" />
                            </div>
                            <div class="grid gap-2">
                                <Label>Date d'imputation division *</Label
                                ><Input v-model="assignmentForm.division_assigned_at" type="date" required /><InputError
                                    :message="assignmentForm.errors.division_assigned_at"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label>Agent responsable</Label
                                ><select v-model="assignmentForm.assigned_agent_id" class="h-10 rounded-md border bg-background px-3 text-sm">
                                    <option value="">À affecter plus tard</option>
                                    <option v-for="agent in filteredAgents" :key="agent.id" :value="agent.id">{{ agent.name }}</option></select
                                ><InputError :message="assignmentForm.errors.assigned_agent_id" />
                            </div>
                            <div class="grid gap-2">
                                <Label>Date d'imputation agent</Label
                                ><Input
                                    v-model="assignmentForm.agent_assigned_at"
                                    type="date"
                                    :required="!!assignmentForm.assigned_agent_id"
                                /><InputError :message="assignmentForm.errors.agent_assigned_at" />
                            </div>
                            <div class="grid gap-2 md:col-span-2">
                                <Label>Divisions contributrices</Label>
                                <ChoiceChips
                                    v-model="assignmentForm.contributing_division_ids"
                                    :options="contributingOptions"
                                    empty="Choisissez d'abord la division pilote."
                                />
                                <InputError :message="assignmentForm.errors.contributing_division_ids" />
                            </div>
                            <div class="grid gap-2 md:col-span-2">
                                <Label>Co-agents</Label>
                                <ChoiceChips
                                    v-if="assignmentForm.assigned_agent_id"
                                    v-model="assignmentForm.co_agent_ids"
                                    :options="coAgentOptions"
                                    empty="Aucun autre agent dans les divisions imputées."
                                />
                                <p v-else class="text-sm text-muted-foreground">Désignez d'abord l'agent responsable.</p>
                                <InputError :message="assignmentForm.errors.co_agent_ids" />
                            </div>
                            <div class="grid gap-2 md:col-span-2">
                                <Label>Instructions DSP</Label
                                ><textarea
                                    v-model="assignmentForm.instructions"
                                    rows="3"
                                    class="rounded-md border bg-background px-3 py-2 text-sm"
                                /><InputError :message="assignmentForm.errors.instructions" />
                            </div>
                            <div class="grid gap-2 md:col-span-2">
                                <Label>Commentaire de suivi</Label><Input v-model="assignmentForm.comment" /><InputError
                                    :message="assignmentForm.errors.comment"
                                />
                            </div>
                            <div class="md:col-span-2"><Button :disabled="assignmentForm.processing">Enregistrer l'affectation</Button></div>
                        </form>
                    </section>

                    <section
                        v-if="permissions.submit && ['assigned_agent', 'correction_requested'].includes(courrier.status)"
                        class="rounded-xl border bg-card p-5 md:p-6"
                    >
                        <div class="mb-5 flex items-center gap-3">
                            <div class="rounded-lg bg-yellow-100 p-2 text-yellow-700 dark:bg-yellow-950 dark:text-yellow-300">
                                <Send class="size-5" />
                            </div>
                            <div>
                                <h2 class="font-semibold">
                                    {{ courrier.status === 'correction_requested' ? 'Renvoyer après correction' : 'Soumettre au Chef de Division' }}
                                </h2>
                                <p class="text-sm text-muted-foreground">
                                    Joignez le projet de réponse, puis transmettez-le au Chef pour validation.
                                </p>
                            </div>
                        </div>
                        <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submitToDivision">
                            <div class="grid gap-2">
                                <Label>Date de production *</Label><Input v-model="submitForm.submitted_at" type="date" required /><InputError
                                    :message="submitForm.errors.submitted_at"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label>Commentaire</Label><Input v-model="submitForm.comment" /><InputError :message="submitForm.errors.comment" />
                            </div>
                            <div class="md:col-span-2">
                                <Button :disabled="submitForm.processing"><Send class="mr-2 size-4" /> Transmettre au Chef</Button>
                            </div>
                        </form>
                    </section>

                    <section
                        v-if="permissions.review && courrier.status === 'pending_division_review'"
                        class="rounded-xl border border-amber-200 bg-amber-50/40 p-5 dark:border-amber-900 dark:bg-amber-950/20 md:p-6"
                    >
                        <div class="mb-5 flex items-center gap-3">
                            <div class="rounded-lg bg-amber-100 p-2 text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                                <ShieldCheck class="size-5" />
                            </div>
                            <div>
                                <h2 class="font-semibold">Validation du Chef de Division</h2>
                                <p class="text-sm text-muted-foreground">Validez le projet ou renvoyez-le à l’agent avec vos corrections.</p>
                            </div>
                        </div>
                        <form class="grid gap-4 md:grid-cols-2" @submit.prevent="review('approve')">
                            <div class="grid gap-2">
                                <Label>Date de validation *</Label><Input v-model="reviewForm.reviewed_at" type="date" required /><InputError
                                    :message="reviewForm.errors.reviewed_at"
                                />
                            </div>
                            <div class="grid gap-2 md:col-span-2">
                                <Label>Commentaire</Label>
                                <textarea
                                    v-model="reviewForm.comment"
                                    rows="3"
                                    class="rounded-md border bg-background px-3 py-2 text-sm"
                                    placeholder="Obligatoire en cas de demande de correction"
                                />
                                <InputError :message="reviewForm.errors.comment || reviewForm.errors.decision" />
                            </div>
                            <div class="flex flex-wrap gap-2 md:col-span-2">
                                <Button type="submit" :disabled="reviewForm.processing">
                                    <ShieldCheck class="mr-2 size-4" /> Valider et transmettre au Directeur
                                </Button>
                                <Button type="button" variant="outline" :disabled="reviewForm.processing" @click="review('reject')">
                                    <RotateCcw class="mr-2 size-4" /> Demander une correction
                                </Button>
                            </div>
                        </form>
                    </section>

                    <section
                        v-if="courrier.status === 'correction_requested' && !permissions.submit"
                        class="rounded-xl border border-red-200 bg-red-50/50 p-5 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/20 dark:text-red-200"
                    >
                        Une correction a été demandée à l’agent responsable. Le motif figure dans l’historique.
                    </section>

                    <section
                        v-if="permissions.sign && ['division_validated', 'submitted'].includes(courrier.status)"
                        class="rounded-xl border border-emerald-200 bg-emerald-50/40 p-5 dark:border-emerald-900 dark:bg-emerald-950/20 md:p-6"
                    >
                        <div class="mb-5 flex items-center gap-3">
                            <div class="rounded-lg bg-emerald-100 p-2 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                                <CheckCircle2 class="size-5" />
                            </div>
                            <div>
                                <h2 class="font-semibold">Signer et clôturer</h2>
                                <p class="text-sm text-muted-foreground">
                                    Le projet a été validé par la division. Enregistrez la date effective de signature.
                                </p>
                            </div>
                        </div>
                        <form class="grid gap-4 md:grid-cols-2" @submit.prevent="sign">
                            <div class="grid gap-2">
                                <Label>Date de signature *</Label><Input v-model="signForm.signed_at" type="date" required /><InputError
                                    :message="signForm.errors.signed_at"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label>Commentaire</Label><Input v-model="signForm.comment" /><InputError :message="signForm.errors.comment" />
                            </div>
                            <div class="md:col-span-2">
                                <Button :disabled="signForm.processing"><CheckCircle2 class="mr-2 size-4" /> Signer et clôturer</Button>
                            </div>
                        </form>
                    </section>
                </div>

                <aside class="rounded-xl border bg-card p-5 md:p-6">
                    <div class="flex items-center gap-2">
                        <Clock3 class="size-5 text-muted-foreground" />
                        <h2 class="font-semibold">Historique</h2>
                    </div>
                    <ol class="mt-6 space-y-0">
                        <li v-for="(event, index) in courrier.events" :key="event.id" class="relative flex gap-3 pb-6">
                            <div v-if="index < (courrier.events?.length ?? 0) - 1" class="absolute left-[7px] top-4 h-full w-px bg-border" />
                            <div class="relative mt-1 size-[15px] shrink-0 rounded-full border-4 border-background bg-amber-500" />
                            <div>
                                <p class="text-sm font-medium">{{ eventLabel(event.event) }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ formatDateTime(event.created_at) }}<span v-if="event.user"> · {{ event.user.name }}</span>
                                </p>
                                <p v-if="event.comment" class="mt-2 rounded-md bg-muted p-2 text-xs">{{ event.comment }}</p>
                            </div>
                        </li>
                    </ol>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
