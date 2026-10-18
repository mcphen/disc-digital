<script setup lang="ts">
import { days, formatDate } from '@/lib/pilotage';
import type { LateList } from '@/types/pilotage';
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight, Lock } from 'lucide-vue-next';

defineProps<{ late: LateList; title: string; description: string; showAgent?: boolean; showDivision?: boolean }>();
</script>

<template>
    <section class="overflow-hidden rounded-2xl border bg-card">
        <div class="flex flex-wrap items-end justify-between gap-2 p-5 pb-3">
            <div>
                <h2 class="font-semibold">{{ title }}</h2>
                <p class="text-sm text-muted-foreground">{{ description }}</p>
            </div>
            <p v-if="late.total" class="text-sm text-muted-foreground">
                <strong class="text-foreground">{{ late.total }}</strong> au total, dont
                <strong class="text-red-600 dark:text-red-400">{{ late.overdue }}</strong> non traités
                <template v-if="late.total > late.items.length"> · {{ late.items.length }} affichés</template>
            </p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-left text-sm">
                <thead class="border-y bg-muted/50 text-xs uppercase text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2.5">N° DISC</th>
                        <th class="px-4 py-2.5">Objet</th>
                        <th v-if="showDivision || showAgent" class="px-4 py-2.5">{{ showDivision ? 'Division / Agent' : 'Agent' }}</th>
                        <th class="px-4 py-2.5">Échéance</th>
                        <th class="px-4 py-2.5">Situation</th>
                        <th class="px-4 py-2.5 text-right">Retard</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="row in late.items" :key="row.id">
                        <td class="px-4 py-2.5 font-medium">
                            <Link
                                v-if="row.can_open"
                                :href="`/courriers/${row.id}`"
                                class="inline-flex items-center gap-1 text-amber-700 hover:underline dark:text-amber-300"
                                >{{ row.registration_number }} <ArrowUpRight class="size-3.5"
                            /></Link>
                            <span v-else class="inline-flex items-center gap-1">{{ row.registration_number }} <Lock class="size-3.5" /></span>
                        </td>
                        <td class="max-w-xs px-4 py-2.5">
                            <p class="line-clamp-1" :class="row.can_open ? '' : 'italic text-muted-foreground'" :title="row.subject">
                                {{ row.subject }}
                            </p>
                        </td>
                        <td v-if="showDivision || showAgent" class="px-4 py-2.5">
                            <template v-if="showDivision">
                                {{ row.division ?? '—' }}
                                <span class="block text-xs text-muted-foreground">{{ row.agent ?? 'Non attribué' }}</span>
                            </template>
                            <template v-else>{{ row.agent ?? 'Non attribué' }}</template>
                        </td>
                        <td class="whitespace-nowrap px-4 py-2.5">{{ formatDate(row.deadline) }}</td>
                        <td class="px-4 py-2.5">
                            <span v-if="row.outcome === 'overdue'" class="font-medium text-red-600 dark:text-red-400">Non traité</span>
                            <span v-else>Traité le {{ formatDate(row.done_at!) }}</span>
                        </td>
                        <td class="px-4 py-2.5 text-right font-semibold tabular-nums">{{ days(row.days_late) }}</td>
                    </tr>
                    <tr v-if="!late.items.length">
                        <td colspan="6" class="px-4 py-10 text-center text-muted-foreground">Aucun courrier en retard sur ce trimestre.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
