<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { User } from '@/types';
import type { Division } from '@/types/disc';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Save } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    editedUser: (User & { division?: Division | null }) | null;
    divisions: Division[];
    roleLabels: Record<string, string>;
}>();
const editing = computed(() => !!props.editedUser);
const form = useForm({
    name: props.editedUser?.name ?? '',
    email: props.editedUser?.email ?? '',
    role: props.editedUser?.role ?? 'agent',
    division_id: props.editedUser?.division_id?.toString() ?? '',
    is_active: props.editedUser?.is_active ?? true,
    password: '',
    password_confirmation: '',
});
const submit = () => (editing.value ? form.put(`/administration/users/${props.editedUser!.id}`) : form.post('/administration/users'));
const needsDivision = computed(() => ['agent', 'division_head'].includes(form.role));
const breadcrumbs = [
    { title: 'Utilisateurs', href: '/administration/users' },
    { title: editing.value ? 'Modifier' : 'Nouveau', href: '#' },
];
</script>

<template>
    <Head :title="editing ? 'Modifier un utilisateur' : 'Nouvel utilisateur'" />
    <AppLayout :breadcrumbs="breadcrumbs"
        ><div class="mx-auto w-full max-w-3xl p-4 md:p-6">
            <div class="mb-5 flex items-center gap-3">
                <Button variant="outline" size="icon" as-child
                    ><Link href="/administration/users"><ArrowLeft class="size-4" /></Link
                ></Button>
                <div>
                    <h1 class="text-2xl font-semibold">{{ editing ? 'Modifier l’utilisateur' : 'Créer un utilisateur' }}</h1>
                    <p class="text-sm text-muted-foreground">Définissez son rôle et son périmètre d’accès.</p>
                </div>
            </div>
            <form class="space-y-5 rounded-xl border bg-card p-5 md:p-6" @submit.prevent="submit">
                <div class="grid gap-5 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label>Nom complet *</Label><Input v-model="form.name" required /><InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label>Email *</Label><Input v-model="form.email" type="email" required /><InputError :message="form.errors.email" />
                    </div>
                    <div class="grid gap-2">
                        <Label>Rôle *</Label
                        ><select v-model="form.role" class="h-10 rounded-md border bg-background px-3 text-sm">
                            <option v-for="(label, role) in roleLabels" :key="role" :value="role">{{ label }}</option></select
                        ><InputError :message="form.errors.role" />
                    </div>
                    <div v-if="needsDivision" class="grid gap-2">
                        <Label>Division *</Label
                        ><select v-model="form.division_id" class="h-10 rounded-md border bg-background px-3 text-sm">
                            <option disabled value="">Sélectionner</option>
                            <option v-for="division in divisions" :key="division.id" :value="division.id">
                                {{ division.code }} — {{ division.name }}
                            </option></select
                        ><InputError :message="form.errors.division_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label>{{ editing ? 'Nouveau mot de passe' : 'Mot de passe *' }}</Label
                        ><Input v-model="form.password" type="password" :required="!editing" /><InputError :message="form.errors.password" />
                    </div>
                    <div class="grid gap-2">
                        <Label>Confirmation</Label><Input v-model="form.password_confirmation" type="password" :required="!!form.password" />
                    </div>
                    <label class="flex items-center gap-3 md:col-span-2"
                        ><input v-model="form.is_active" type="checkbox" class="rounded border-input" /><span class="text-sm"
                            >Compte actif</span
                        ></label
                    >
                </div>
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" as-child><Link href="/administration/users">Annuler</Link></Button
                    ><Button :disabled="form.processing"><Save class="mr-2 size-4" /> Enregistrer</Button>
                </div>
            </form>
        </div></AppLayout
    >
</template>
