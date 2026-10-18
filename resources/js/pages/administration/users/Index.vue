<script setup lang="ts">
import MobileDataCard from '@/components/disc/MobileDataCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { User } from '@/types';
import type { Division, Paginated } from '@/types/disc';
import { Head, Link, router } from '@inertiajs/vue3';
import { Mail, Pencil, Search, ShieldCheck, UserPlus } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    users: Paginated<User & { division?: Division | null }>;
    search: string;
    roleLabels: Record<string, string>;
}>();

const query = ref(props.search);
const searchUsers = () => router.get('/administration/users', { search: query.value }, { preserveState: true, replace: true });
const breadcrumbs = [
    { title: 'Administration', href: '/administration/users' },
    { title: 'Utilisateurs', href: '/administration/users' },
];
</script>

<template>
    <Head title="Utilisateurs" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-5 p-4 md:p-6">
            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-2xl font-semibold">Utilisateurs et rôles</h1>
                    <p class="text-sm text-muted-foreground">Les comptes sont désormais créés uniquement par un administrateur.</p>
                </div>
                <Button class="w-full sm:w-auto" as-child
                    ><Link href="/administration/users/create"><UserPlus class="mr-2 size-4" /> Nouvel utilisateur</Link></Button
                >
            </div>
            <form class="flex max-w-xl flex-col gap-2 sm:flex-row" @submit.prevent="searchUsers">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-3 size-4 text-muted-foreground" /><Input
                        v-model="query"
                        class="pl-9"
                        placeholder="Nom ou adresse email"
                    />
                </div>
                <Button variant="outline" class="sm:w-auto">Rechercher</Button>
            </form>
            <div class="overflow-hidden rounded-xl border bg-card">
                <div class="hidden overflow-x-auto md:block">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b bg-muted/50 text-xs uppercase text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3">Utilisateur</th>
                                <th class="px-4 py-3">Rôle</th>
                                <th class="px-4 py-3">Division</th>
                                <th class="px-4 py-3">Statut</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="user in users.data" :key="user.id">
                                <td class="px-4 py-4">
                                    <p class="font-medium">{{ user.name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ user.email }}</p>
                                </td>
                                <td class="px-4 py-4">{{ roleLabels[user.role] ?? user.role }}</td>
                                <td class="px-4 py-4">{{ user.division?.code ?? '—' }}</td>
                                <td class="px-4 py-4">
                                    <span
                                        class="rounded-full px-2 py-1 text-xs font-medium"
                                        :class="user.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'"
                                        >{{ user.is_active ? 'Actif' : 'Inactif' }}</span
                                    >
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <Button variant="outline" size="sm" as-child
                                        ><Link :href="`/administration/users/${user.id}/edit`">Modifier</Link></Button
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="disc-mobile-stack md:hidden">
                    <MobileDataCard v-for="user in users.data" :key="user.id">
                        <template #header>
                            <p class="truncate font-semibold">{{ user.name }}</p>
                            <p class="mt-1 flex items-center gap-1.5 truncate text-xs text-muted-foreground">
                                <Mail class="size-3.5 shrink-0" /> {{ user.email }}
                            </p>
                        </template>
                        <template #badge>
                            <span
                                class="rounded-full px-2.5 py-1 text-[11px] font-semibold"
                                :class="user.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'"
                                >{{ user.is_active ? 'Actif' : 'Inactif' }}</span
                            >
                        </template>

                        <dl class="disc-mobile-meta">
                            <div>
                                <dt>Rôle</dt>
                                <dd class="flex items-center gap-1.5">
                                    <ShieldCheck class="size-3.5 text-amber-600" /> {{ roleLabels[user.role] ?? user.role }}
                                </dd>
                            </div>
                            <div>
                                <dt>Division</dt>
                                <dd>{{ user.division?.code ?? 'Non rattaché' }}</dd>
                            </div>
                        </dl>

                        <template #footer>
                            <Button variant="outline" class="w-full" as-child>
                                <Link :href="`/administration/users/${user.id}/edit`"><Pencil class="mr-2 size-4" /> Modifier le compte</Link>
                            </Button>
                        </template>
                    </MobileDataCard>
                    <div v-if="!users.data.length" class="px-4 py-12 text-center text-sm text-muted-foreground">
                        Aucun utilisateur ne correspond à la recherche.
                    </div>
                </div>
                <div v-if="users.last_page > 1" class="flex gap-1 overflow-x-auto border-t px-4 py-3">
                    <Button
                        v-for="link in users.links"
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
    </AppLayout>
</template>
