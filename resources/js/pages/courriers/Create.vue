<script setup lang="ts">
import ChoiceChips from '@/components/disc/ChoiceChips.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { CourrierType, Division } from '@/types/disc';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, FileText, Paperclip, Save, Upload, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{ divisions: Division[]; types: CourrierType[] }>();

const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'Africa/Dakar' }).format(new Date());
const form = useForm({
    arrival_date: today,
    reference: '',
    origin_service: '',
    courrier_type_id: '',
    subject: '',
    arrival_number: '',
    division_id: '',
    division_assigned_at: '',
    contributing_division_ids: [] as number[],
    instructions: '',
    notes: '',
    attachments: [] as File[],
});

// Divisions contributrices : toutes sauf la pilote, et seulement une fois la pilote choisie.
const contributingOptions = computed(() =>
    form.division_id
        ? props.divisions
              .filter((division) => division.id !== Number(form.division_id))
              .map((division) => ({ id: division.id, label: division.code, hint: division.name }))
        : [],
);
watch(
    () => form.division_id,
    (id) => (form.contributing_division_ids = id ? form.contributing_division_ids.filter((value) => value !== Number(id)) : []),
);

const MAX_FILES = 10;
const MAX_SIZE = 10 * 1024 * 1024;
const ACCEPTED = '.pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx';

const fileInput = ref<HTMLInputElement | null>(null);
const isDragging = ref(false);
const fileError = ref('');

const attachmentErrors = computed(() =>
    Object.entries(form.errors)
        .filter(([key]) => key === 'attachments' || key.startsWith('attachments.'))
        .map(([, message]) => message),
);

const formatSize = (bytes: number) =>
    bytes < 1024 * 1024 ? `${Math.max(1, Math.round(bytes / 1024))} Ko` : `${(bytes / 1024 / 1024).toFixed(1)} Mo`;

const addFiles = (files: FileList | null) => {
    fileError.value = '';
    if (!files) return;
    const extensions = ACCEPTED.split(',');
    for (const file of Array.from(files)) {
        if (form.attachments.length >= MAX_FILES) {
            fileError.value = `${MAX_FILES} pièces jointes maximum.`;
            break;
        }
        if (!extensions.some((ext) => file.name.toLowerCase().endsWith(ext))) {
            fileError.value = `« ${file.name} » : format non accepté.`;
            continue;
        }
        if (file.size > MAX_SIZE) {
            fileError.value = `« ${file.name} » dépasse 10 Mo.`;
            continue;
        }
        if (form.attachments.some((f) => f.name === file.name && f.size === file.size)) continue;
        form.attachments.push(file);
    }
    if (fileInput.value) fileInput.value.value = '';
};

const onDrop = (event: DragEvent) => {
    isDragging.value = false;
    addFiles(event.dataTransfer?.files ?? null);
};

const removeFile = (index: number) => form.attachments.splice(index, 1);

const submit = () => form.post('/courriers', { forceFormData: true });
const breadcrumbs = [
    { title: 'Courriers', href: '/courriers' },
    { title: 'Nouveau', href: '/courriers/create' },
];
</script>

<template>
    <Head title="Nouveau courrier" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-5 p-4 md:p-6">
            <div class="flex items-center gap-3">
                <Button variant="outline" size="icon" as-child
                    ><Link href="/courriers"><ArrowLeft class="size-4" /></Link
                ></Button>
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Enregistrer un courrier</h1>
                    <p class="text-sm text-muted-foreground">Les délais applicables seront fixés automatiquement selon le type.</p>
                </div>
            </div>

            <form class="space-y-5" @submit.prevent="submit">
                <section class="rounded-xl border bg-card p-5 md:p-6">
                    <div class="mb-5">
                        <h2 class="font-semibold">Réception et identification</h2>
                        <p class="text-sm text-muted-foreground">Informations présentes sur le courrier à son arrivée.</p>
                    </div>
                    <div class="grid gap-5 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="arrival_date">Date d'arrivée *</Label
                            ><Input id="arrival_date" v-model="form.arrival_date" type="date" required /><InputError
                                :message="form.errors.arrival_date"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="arrival_number">N° courrier arrivée</Label
                            ><Input id="arrival_number" v-model="form.arrival_number" placeholder="Ex. 607" /><InputError
                                :message="form.errors.arrival_number"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="reference">Référence du courrier</Label
                            ><Input id="reference" v-model="form.reference" placeholder="N° et date du courrier" /><InputError
                                :message="form.errors.reference"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="origin_service">Service d'origine *</Label
                            ><Input id="origin_service" v-model="form.origin_service" required placeholder="Émetteur du courrier" /><InputError
                                :message="form.errors.origin_service"
                            />
                        </div>
                        <div class="grid gap-2 md:col-span-2">
                            <Label for="courrier_type_id">Type de courrier *</Label
                            ><select
                                id="courrier_type_id"
                                v-model="form.courrier_type_id"
                                required
                                class="h-10 rounded-md border bg-background px-3 text-sm"
                            >
                                <option disabled value="">Sélectionner un type</option>
                                <option v-for="type in types" :key="type.id" :value="type.id">
                                    {{ type.name
                                    }}<template v-if="type.requires_response">
                                        — {{ type.agent_delay_days }} j agent + {{ type.director_delay_days }} j Directeur</template
                                    >
                                </option></select
                            ><InputError :message="form.errors.courrier_type_id" />
                        </div>
                        <div class="grid gap-2 md:col-span-2">
                            <Label for="subject">Objet / Diligences *</Label
                            ><textarea
                                id="subject"
                                v-model="form.subject"
                                required
                                rows="4"
                                class="rounded-md border bg-background px-3 py-2 text-sm"
                                placeholder="Résumé de l'objet et diligences attendues"
                            /><InputError :message="form.errors.subject" />
                        </div>
                    </div>
                </section>

                <section class="rounded-xl border bg-card p-5 md:p-6">
                    <div class="mb-5">
                        <h2 class="font-semibold">Première imputation <span class="font-normal text-muted-foreground">(facultatif)</span></h2>
                        <p class="text-sm text-muted-foreground">Cette étape peut aussi être réalisée plus tard depuis la fiche du courrier.</p>
                    </div>
                    <div class="grid gap-5 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="division_id">Division pilote</Label
                            ><select id="division_id" v-model="form.division_id" class="h-10 rounded-md border bg-background px-3 text-sm">
                                <option value="">Non déterminée</option>
                                <option v-for="division in divisions" :key="division.id" :value="division.id">
                                    {{ division.code }} — {{ division.name }}
                                </option></select
                            ><InputError :message="form.errors.division_id" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="division_assigned_at">Date d'imputation à la division</Label
                            ><Input id="division_assigned_at" v-model="form.division_assigned_at" type="date" /><InputError
                                :message="form.errors.division_assigned_at"
                            />
                        </div>
                        <div class="grid gap-2 md:col-span-2">
                            <Label>Divisions contributrices</Label>
                            <ChoiceChips
                                v-model="form.contributing_division_ids"
                                :options="contributingOptions"
                                empty="Choisissez d'abord la division pilote."
                            />
                            <InputError :message="form.errors.contributing_division_ids" />
                        </div>
                        <div class="grid gap-2 md:col-span-2">
                            <Label for="instructions">Instructions DSP</Label
                            ><textarea
                                id="instructions"
                                v-model="form.instructions"
                                rows="3"
                                class="rounded-md border bg-background px-3 py-2 text-sm"
                            /><InputError :message="form.errors.instructions" />
                        </div>
                        <div class="grid gap-2 md:col-span-2">
                            <Label for="notes">Notes internes</Label
                            ><textarea
                                id="notes"
                                v-model="form.notes"
                                rows="2"
                                class="rounded-md border bg-background px-3 py-2 text-sm"
                            /><InputError :message="form.errors.notes" />
                        </div>
                    </div>
                </section>

                <section class="rounded-xl border bg-card p-5 md:p-6">
                    <div class="mb-5">
                        <h2 class="font-semibold">Pièces jointes <span class="font-normal text-muted-foreground">(facultatif)</span></h2>
                        <p class="text-sm text-muted-foreground">Courrier scanné, annexes… PDF, images, Word ou Excel — 10 Mo max par fichier.</p>
                    </div>
                    <div
                        role="button"
                        tabindex="0"
                        :class="[
                            'flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed px-4 py-8 text-center transition-colors',
                            isDragging ? 'border-primary bg-primary/5' : 'border-border hover:border-primary/50 hover:bg-muted/50',
                        ]"
                        @click="fileInput?.click()"
                        @keydown.enter.prevent="fileInput?.click()"
                        @keydown.space.prevent="fileInput?.click()"
                        @dragover.prevent="isDragging = true"
                        @dragleave.prevent="isDragging = false"
                        @drop.prevent="onDrop"
                    >
                        <Upload class="size-6 text-muted-foreground" />
                        <p class="text-sm"><span class="font-medium text-primary">Choisir des fichiers</span> ou les glisser ici</p>
                        <p class="text-xs text-muted-foreground">{{ form.attachments.length }} / {{ MAX_FILES }} fichiers</p>
                        <input
                            ref="fileInput"
                            type="file"
                            multiple
                            :accept="ACCEPTED"
                            class="hidden"
                            @change="addFiles(($event.target as HTMLInputElement).files)"
                        />
                    </div>
                    <InputError class="mt-2" :message="fileError" />
                    <InputError v-for="(message, i) in attachmentErrors" :key="i" class="mt-2" :message="message" />
                    <ul v-if="form.attachments.length" class="mt-4 divide-y rounded-xl border">
                        <li v-for="(file, index) in form.attachments" :key="`${file.name}-${file.size}`" class="flex items-center gap-3 px-3 py-2.5">
                            <FileText class="size-4 shrink-0 text-muted-foreground" />
                            <span class="min-w-0 flex-1 truncate text-sm">{{ file.name }}</span>
                            <span class="shrink-0 text-xs text-muted-foreground">{{ formatSize(file.size) }}</span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="size-8 shrink-0"
                                :aria-label="`Retirer ${file.name}`"
                                @click="removeFile(index)"
                                ><X class="size-4"
                            /></Button>
                        </li>
                    </ul>
                    <p v-if="form.progress" class="mt-3 flex items-center gap-2 text-xs text-muted-foreground">
                        <Paperclip class="size-3.5" /> Envoi des fichiers… {{ form.progress.percentage }} %
                    </p>
                </section>

                <div class="flex flex-col-reverse justify-end gap-3 sm:flex-row">
                    <Button type="button" variant="outline" class="w-full sm:w-auto" as-child><Link href="/courriers">Annuler</Link></Button
                    ><Button type="submit" class="w-full sm:w-auto" :disabled="form.processing"><Save class="mr-2 size-4" /> Enregistrer</Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
