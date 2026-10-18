<script setup lang="ts">
import { Input } from '@/components/ui/input';
import {
    AlertTriangle,
    BarChart3,
    BellRing,
    CheckCircle2,
    ChevronDown,
    CircleHelp,
    FileCheck2,
    FilePlus2,
    FolderSearch2,
    KeyRound,
    ListChecks,
    LockKeyhole,
    Mail,
    Paperclip,
    Search,
    ShieldCheck,
    UserCheck,
    UsersRound,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

const search = ref('');

const sectionKeywords: Record<string, string> = {
    connexion: 'connexion connecter email mot de passe oublié compte accès erreur session',
    navigation: 'navigation tableau de bord recherche notifications menu raccourci',
    courrier: 'courrier enregistrer créer consulter référence pièce jointe historique',
    affectation: 'affectation division agent masse sélectionner instruction',
    traitement: 'traitement rôle secrétariat directeur chef division agent validation correction signature',
    alertes: 'alerte vert jaune rouge délai retard échéance priorité',
    rapports: 'rapport export csv imprimer pdf indicateur statistique',
    faq: 'problème aide faq vide introuvable permission document notification',
};

const normalizedSearch = computed(() => search.value.trim().toLocaleLowerCase('fr'));
const visible = (section: string) => !normalizedSearch.value || sectionKeywords[section].includes(normalizedSearch.value);
const hasResults = computed(() => Object.keys(sectionKeywords).some((section) => visible(section)));

const anchors = [
    { id: 'connexion', label: 'Connexion', icon: KeyRound },
    { id: 'navigation', label: 'Navigation', icon: FolderSearch2 },
    { id: 'courrier', label: 'Courriers', icon: FileCheck2 },
    { id: 'affectation', label: 'Affectation', icon: ListChecks },
    { id: 'traitement', label: 'Circuit', icon: UsersRound },
    { id: 'alertes', label: 'Alertes', icon: BellRing },
    { id: 'rapports', label: 'Rapports', icon: BarChart3 },
    { id: 'faq', label: 'Dépannage', icon: CircleHelp },
];

const roles = [
    { name: 'Secrétariat', description: 'Enregistre le courrier entrant et réalise la première orientation.', color: 'bg-sky-500' },
    { name: 'Directeur', description: 'Oriente, supervise les priorités et signe les réponses validées.', color: 'bg-amber-500' },
    { name: 'Chef de division', description: 'Affecte aux agents, contrôle le travail et demande les corrections.', color: 'bg-violet-500' },
    { name: 'Agent', description: 'Traite ses courriers, joint les documents et soumet sa réponse.', color: 'bg-emerald-500' },
    { name: 'Administrateur', description: 'Gère les comptes, les paramètres, les imports et tous les circuits.', color: 'bg-stone-700' },
    { name: 'Auditeur', description: 'Consulte les dossiers, les historiques et les indicateurs autorisés.', color: 'bg-rose-500' },
];
</script>

<template>
    <div class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8 lg:py-10">
        <section class="relative overflow-hidden rounded-[28px] bg-[#1c1a17] p-6 text-white shadow-2xl shadow-amber-950/15 sm:p-8 lg:p-10">
            <div class="absolute inset-x-0 top-0 grid h-1 grid-cols-3">
                <span class="bg-[#00853f]" /><span class="bg-[#fdef42]" /><span class="bg-[#e31b23]" />
            </div>
            <div class="absolute -right-20 -top-24 size-72 rounded-full border border-white/10" />
            <div class="relative max-w-3xl">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-300">Centre d’aide DISC Digital</p>
                <h1 class="mt-3 text-3xl font-semibold tracking-[-0.04em] sm:text-4xl">Comment pouvons-nous vous aider ?</h1>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-stone-300 sm:text-base">
                    Retrouvez les étapes de connexion et les gestes essentiels pour suivre un courrier du dépôt jusqu’à la clôture.
                </p>
                <div class="relative mt-6 max-w-xl">
                    <Search class="pointer-events-none absolute left-4 top-3.5 size-5 text-stone-400" />
                    <Input
                        v-model="search"
                        type="search"
                        class="h-12 border-white/15 bg-white/10 pl-12 text-white placeholder:text-stone-400"
                        placeholder="Rechercher : connexion, affectation, retard…"
                    />
                </div>
            </div>
        </section>

        <nav class="mt-5 flex gap-2 overflow-x-auto pb-2" aria-label="Sommaire du guide">
            <a
                v-for="anchor in anchors"
                :key="anchor.id"
                :href="`#${anchor.id}`"
                class="inline-flex min-h-10 shrink-0 items-center gap-2 rounded-full border bg-card/80 px-3.5 text-sm font-medium transition hover:border-amber-400 hover:bg-amber-50 dark:hover:bg-amber-950/30"
            >
                <component :is="anchor.icon" class="size-4 text-amber-600" /> {{ anchor.label }}
            </a>
        </nav>

        <div v-if="!hasResults" class="mt-8 rounded-3xl border bg-card p-10 text-center">
            <Search class="mx-auto size-8 text-muted-foreground" />
            <h2 class="mt-4 font-semibold">Aucun sujet trouvé</h2>
            <p class="mt-1 text-sm text-muted-foreground">Essayez un mot plus simple, comme « connexion », « agent » ou « rapport ».</p>
        </div>

        <div v-else class="mt-8 grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_300px]">
            <main class="min-w-0 space-y-6">
                <section v-if="visible('connexion')" id="connexion" class="scroll-mt-24 rounded-[24px] border bg-card p-5 sm:p-7">
                    <div class="flex items-start gap-3">
                        <span
                            class="grid size-11 shrink-0 place-items-center rounded-2xl bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300"
                            ><LockKeyhole class="size-5"
                        /></span>
                        <div>
                            <p class="disc-eyebrow">Bien démarrer</p>
                            <h2 class="mt-1 text-xl font-semibold">Se connecter à la plateforme</h2>
                        </div>
                    </div>
                    <ol class="mt-6 grid gap-3 sm:grid-cols-3">
                        <li class="rounded-2xl bg-muted/55 p-4">
                            <span class="text-xs font-bold text-amber-700">01</span>
                            <h3 class="mt-2 font-semibold">Ouvrir la connexion</h3>
                            <p class="mt-1 text-sm leading-6 text-muted-foreground">Accédez à DISC Digital puis choisissez « Se connecter ».</p>
                        </li>
                        <li class="rounded-2xl bg-muted/55 p-4">
                            <span class="text-xs font-bold text-amber-700">02</span>
                            <h3 class="mt-2 font-semibold">Saisir ses accès</h3>
                            <p class="mt-1 text-sm leading-6 text-muted-foreground">
                                Utilisez l’adresse professionnelle et le mot de passe transmis par l’administrateur.
                            </p>
                        </li>
                        <li class="rounded-2xl bg-muted/55 p-4">
                            <span class="text-xs font-bold text-amber-700">03</span>
                            <h3 class="mt-2 font-semibold">Ouvrir son espace</h3>
                            <p class="mt-1 text-sm leading-6 text-muted-foreground">
                                Après validation, vous arrivez sur le tableau de bord adapté à votre rôle.
                            </p>
                        </li>
                    </ol>
                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        <div
                            class="rounded-2xl border border-emerald-200 bg-emerald-50/70 p-4 text-sm dark:border-emerald-900 dark:bg-emerald-950/20"
                        >
                            <p class="font-semibold text-emerald-800 dark:text-emerald-300">Bon réflexe</p>
                            <p class="mt-1 leading-6 text-emerald-800/80 dark:text-emerald-200/80">
                                Activez « Rester connecté » uniquement sur votre poste professionnel personnel.
                            </p>
                        </div>
                        <div class="rounded-2xl border border-amber-200 bg-amber-50/70 p-4 text-sm dark:border-amber-900 dark:bg-amber-950/20">
                            <p class="font-semibold text-amber-800 dark:text-amber-300">Accès refusé ?</p>
                            <p class="mt-1 leading-6 text-amber-800/80 dark:text-amber-200/80">
                                Vérifiez l’adresse et le verrouillage majuscule. Sinon, utilisez « Mot de passe oublié » ou contactez l’administrateur
                                DISC.
                            </p>
                        </div>
                    </div>
                </section>

                <section v-if="visible('navigation')" id="navigation" class="scroll-mt-24 rounded-[24px] border bg-card p-5 sm:p-7">
                    <div class="flex items-start gap-3">
                        <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300"
                            ><FolderSearch2 class="size-5"
                        /></span>
                        <div>
                            <p class="disc-eyebrow">Repères</p>
                            <h2 class="mt-1 text-xl font-semibold">Se déplacer rapidement</h2>
                        </div>
                    </div>
                    <div class="mt-6 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-2xl border p-4">
                            <h3 class="font-semibold">Menu latéral</h3>
                            <p class="mt-2 text-sm leading-6 text-muted-foreground">
                                Il regroupe le tableau de bord, les courriers, vos files de travail, les notifications et les rapports autorisés.
                            </p>
                        </div>
                        <div class="rounded-2xl border p-4">
                            <h3 class="font-semibold">Recherche globale</h3>
                            <p class="mt-2 text-sm leading-6 text-muted-foreground">
                                Utilisez la loupe dans l’en-tête ou le raccourci
                                <kbd class="rounded border bg-muted px-1.5 py-0.5 text-xs">Ctrl K</kbd> pour chercher une référence, un objet ou un
                                service.
                            </p>
                        </div>
                        <div class="rounded-2xl border p-4">
                            <h3 class="font-semibold">Files de travail</h3>
                            <p class="mt-2 text-sm leading-6 text-muted-foreground">
                                Les compteurs « À valider », « À corriger » ou « À signer » conduisent directement aux courriers qui demandent une
                                action.
                            </p>
                        </div>
                        <div class="rounded-2xl border p-4">
                            <h3 class="font-semibold">Sur téléphone</h3>
                            <p class="mt-2 text-sm leading-6 text-muted-foreground">
                                Ouvrez le menu avec l’icône en haut à gauche. Les listes apparaissent sous forme de cartes faciles à parcourir.
                            </p>
                        </div>
                    </div>
                </section>

                <section v-if="visible('courrier')" id="courrier" class="scroll-mt-24 rounded-[24px] border bg-card p-5 sm:p-7">
                    <div class="flex items-start gap-3">
                        <span
                            class="grid size-11 shrink-0 place-items-center rounded-2xl bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
                            ><FilePlus2 class="size-5"
                        /></span>
                        <div>
                            <p class="disc-eyebrow">Dossier</p>
                            <h2 class="mt-1 text-xl font-semibold">Créer et consulter un courrier</h2>
                        </div>
                    </div>
                    <div class="mt-6 space-y-4 text-sm leading-6">
                        <div class="flex gap-3">
                            <CheckCircle2 class="mt-0.5 size-5 shrink-0 text-emerald-500" />
                            <p>
                                <strong>Enregistrement :</strong> renseignez la date d’arrivée, le service d’origine, la référence, le type et
                                l’objet. Les délais sont calculés selon le type choisi.
                            </p>
                        </div>
                        <div class="flex gap-3">
                            <CheckCircle2 class="mt-0.5 size-5 shrink-0 text-emerald-500" />
                            <p>
                                <strong>Consultation :</strong> touchez une carte ou cliquez sur une ligne pour ouvrir la fiche complète du courrier.
                            </p>
                        </div>
                        <div class="flex gap-3">
                            <Paperclip class="mt-0.5 size-5 shrink-0 text-amber-600" />
                            <p>
                                <strong>Pièces jointes :</strong> ajoutez les documents utiles depuis la fiche. Vérifiez leur nom avant l’envoi pour
                                faciliter les recherches.
                            </p>
                        </div>
                        <div class="flex gap-3">
                            <ShieldCheck class="mt-0.5 size-5 shrink-0 text-sky-600" />
                            <p>
                                <strong>Historique :</strong> chaque affectation, validation, correction et signature est enregistrée avec son auteur
                                et sa date.
                            </p>
                        </div>
                    </div>
                </section>

                <section v-if="visible('affectation')" id="affectation" class="scroll-mt-24 rounded-[24px] border bg-card p-5 sm:p-7">
                    <div class="flex items-start gap-3">
                        <span
                            class="grid size-11 shrink-0 place-items-center rounded-2xl bg-violet-100 text-violet-700 dark:bg-violet-950 dark:text-violet-300"
                            ><ListChecks class="size-5"
                        /></span>
                        <div>
                            <p class="disc-eyebrow">Organisation</p>
                            <h2 class="mt-1 text-xl font-semibold">Affecter un ou plusieurs courriers</h2>
                        </div>
                    </div>
                    <ol class="mt-6 space-y-3 text-sm">
                        <li class="flex gap-3 rounded-2xl bg-muted/50 p-4">
                            <span class="font-bold text-amber-700">1.</span>
                            <p>Ouvrez la fiche pour une affectation unique, ou « Affectation en masse » pour traiter plusieurs courriers.</p>
                        </li>
                        <li class="flex gap-3 rounded-2xl bg-muted/50 p-4">
                            <span class="font-bold text-amber-700">2.</span>
                            <p>Sélectionnez la division. Le choix d’un agent devient alors disponible dans cette division.</p>
                        </li>
                        <li class="flex gap-3 rounded-2xl bg-muted/50 p-4">
                            <span class="font-bold text-amber-700">3.</span>
                            <p>Vérifiez les dates d’imputation, ajoutez les instructions utiles puis confirmez l’opération.</p>
                        </li>
                    </ol>
                </section>

                <section v-if="visible('traitement')" id="traitement" class="scroll-mt-24 rounded-[24px] border bg-card p-5 sm:p-7">
                    <div class="flex items-start gap-3">
                        <span
                            class="grid size-11 shrink-0 place-items-center rounded-2xl bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300"
                            ><UsersRound class="size-5"
                        /></span>
                        <div>
                            <p class="disc-eyebrow">Responsabilités</p>
                            <h2 class="mt-1 text-xl font-semibold">Le circuit selon votre rôle</h2>
                        </div>
                    </div>
                    <div class="mt-6 grid gap-3 sm:grid-cols-2">
                        <article v-for="role in roles" :key="role.name" class="rounded-2xl border p-4">
                            <div class="flex items-center gap-2">
                                <span class="size-2.5 rounded-full" :class="role.color" />
                                <h3 class="font-semibold">{{ role.name }}</h3>
                            </div>
                            <p class="mt-2 text-sm leading-6 text-muted-foreground">{{ role.description }}</p>
                        </article>
                    </div>
                    <div class="mt-5 flex flex-wrap items-center gap-2 text-xs font-semibold">
                        <span class="rounded-full bg-slate-100 px-3 py-1.5 text-slate-700">Enregistré</span><span>→</span
                        ><span class="rounded-full bg-sky-100 px-3 py-1.5 text-sky-700">Affecté</span><span>→</span
                        ><span class="rounded-full bg-violet-100 px-3 py-1.5 text-violet-700">À valider</span><span>→</span
                        ><span class="rounded-full bg-amber-100 px-3 py-1.5 text-amber-800">À signer</span><span>→</span
                        ><span class="rounded-full bg-emerald-100 px-3 py-1.5 text-emerald-700">Signé</span>
                    </div>
                </section>

                <section v-if="visible('alertes')" id="alertes" class="scroll-mt-24 rounded-[24px] border bg-card p-5 sm:p-7">
                    <div class="flex items-start gap-3">
                        <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300"
                            ><AlertTriangle class="size-5"
                        /></span>
                        <div>
                            <p class="disc-eyebrow">Délais</p>
                            <h2 class="mt-1 text-xl font-semibold">Comprendre les alertes</h2>
                        </div>
                    </div>
                    <div class="mt-6 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-4 dark:border-emerald-900 dark:bg-emerald-950/20">
                            <span class="inline-flex items-center gap-2 font-semibold text-emerald-700"
                                ><i class="size-2.5 rounded-full bg-emerald-500" /> Verte</span
                            >
                            <p class="mt-2 text-sm text-muted-foreground">Le traitement reste dans les temps.</p>
                        </div>
                        <div class="rounded-2xl border border-amber-200 bg-amber-50/60 p-4 dark:border-amber-900 dark:bg-amber-950/20">
                            <span class="inline-flex items-center gap-2 font-semibold text-amber-700"
                                ><i class="size-2.5 rounded-full bg-amber-500" /> Jaune</span
                            >
                            <p class="mt-2 text-sm text-muted-foreground">L’échéance approche : vérifiez l’avancement.</p>
                        </div>
                        <div class="rounded-2xl border border-red-200 bg-red-50/60 p-4 dark:border-red-900 dark:bg-red-950/20">
                            <span class="inline-flex items-center gap-2 font-semibold text-red-700"
                                ><i class="size-2.5 rounded-full bg-red-500" /> Rouge</span
                            >
                            <p class="mt-2 text-sm text-muted-foreground">Le courrier nécessite une intervention prioritaire.</p>
                        </div>
                    </div>
                </section>

                <section v-if="visible('rapports')" id="rapports" class="scroll-mt-24 rounded-[24px] border bg-card p-5 sm:p-7">
                    <div class="flex items-start gap-3">
                        <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300"
                            ><BarChart3 class="size-5"
                        /></span>
                        <div>
                            <p class="disc-eyebrow">Pilotage</p>
                            <h2 class="mt-1 text-xl font-semibold">Consulter et exporter les rapports</h2>
                        </div>
                    </div>
                    <p class="mt-5 text-sm leading-6 text-muted-foreground">
                        Filtrez par période, division, type ou statut. Les indicateurs se recalculent automatiquement. « Export CSV » récupère toutes
                        les lignes correspondant aux filtres ; « Imprimer / PDF » prépare une version lisible du rapport.
                    </p>
                </section>

                <section v-if="visible('faq')" id="faq" class="scroll-mt-24 rounded-[24px] border bg-card p-5 sm:p-7">
                    <div class="flex items-start gap-3">
                        <span
                            class="grid size-11 shrink-0 place-items-center rounded-2xl bg-stone-100 text-stone-700 dark:bg-stone-800 dark:text-stone-300"
                            ><CircleHelp class="size-5"
                        /></span>
                        <div>
                            <p class="disc-eyebrow">Dépannage</p>
                            <h2 class="mt-1 text-xl font-semibold">Questions fréquentes</h2>
                        </div>
                    </div>
                    <div class="mt-6 divide-y rounded-2xl border px-4">
                        <details class="group py-4">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 font-semibold">
                                Je ne retrouve pas un courrier.<ChevronDown class="size-4 transition group-open:rotate-180" />
                            </summary>
                            <p class="mt-3 text-sm leading-6 text-muted-foreground">
                                Effacez les filtres actifs, recherchez avec une partie de la référence ou de l’objet, puis vérifiez la file « Tous ».
                                Votre rôle peut limiter les courriers visibles.
                            </p>
                        </details>
                        <details class="group py-4">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 font-semibold">
                                Je ne vois pas un bouton d’action.<ChevronDown class="size-4 transition group-open:rotate-180" />
                            </summary>
                            <p class="mt-3 text-sm leading-6 text-muted-foreground">
                                Les actions dépendent de votre rôle et de l’état du courrier. Par exemple, seul le chef de division valide le travail
                                de l’agent.
                            </p>
                        </details>
                        <details class="group py-4">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 font-semibold">
                                Une pièce jointe ne s’ouvre pas.<ChevronDown class="size-4 transition group-open:rotate-180" />
                            </summary>
                            <p class="mt-3 text-sm leading-6 text-muted-foreground">
                                Téléchargez à nouveau le fichier depuis la fiche et vérifiez qu’une application compatible est installée. Si le
                                problème persiste, signalez le nom du courrier et du fichier à l’administrateur.
                            </p>
                        </details>
                        <details class="group py-4">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 font-semibold">
                                La plateforme semble figée.<ChevronDown class="size-4 transition group-open:rotate-180" />
                            </summary>
                            <p class="mt-3 text-sm leading-6 text-muted-foreground">
                                Rechargez la page. Si votre session a expiré, reconnectez-vous. Évitez d’envoyer plusieurs fois le même formulaire
                                pendant le chargement.
                            </p>
                        </details>
                    </div>
                </section>
            </main>

            <aside class="space-y-4 lg:sticky lg:top-24">
                <div class="rounded-[22px] border bg-card p-5">
                    <div class="flex items-center gap-3">
                        <span class="grid size-10 place-items-center rounded-2xl bg-amber-100 text-amber-700"><Mail class="size-5" /></span>
                        <div>
                            <h2 class="font-semibold">Besoin d’assistance ?</h2>
                            <p class="text-xs text-muted-foreground">Préparez ces informations</p>
                        </div>
                    </div>
                    <ul class="mt-4 space-y-2 text-sm text-muted-foreground">
                        <li>• Votre nom et votre rôle</li>
                        <li>• Le numéro DISC concerné</li>
                        <li>• Une capture du message affiché</li>
                        <li>• L’action que vous tentiez</li>
                    </ul>
                    <p class="mt-4 rounded-xl bg-muted/60 p-3 text-xs leading-5">
                        Adressez ensuite la demande à l’administrateur DISC de votre structure. Ne transmettez jamais votre mot de passe.
                    </p>
                </div>
                <div class="rounded-[22px] border border-emerald-200 bg-emerald-50/60 p-5 dark:border-emerald-900 dark:bg-emerald-950/20">
                    <UserCheck class="size-5 text-emerald-600" />
                    <h2 class="mt-3 font-semibold">Protection des données</h2>
                    <p class="mt-2 text-sm leading-6 text-muted-foreground">
                        Fermez votre session lorsque vous quittez un poste partagé et ne téléchargez les pièces que si votre mission l’exige.
                    </p>
                </div>
            </aside>
        </div>
    </div>
</template>
