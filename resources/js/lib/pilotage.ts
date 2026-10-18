import type { Standing } from '@/types/pilotage';
import { AlertOctagon, CheckCircle2, CircleDashed, Eye, MinusCircle } from 'lucide-vue-next';

export const standingMeta: Record<Standing, { label: string; icon: typeof CheckCircle2; badge: string; dot: string }> = {
    good: {
        label: 'Objectif atteint',
        icon: CheckCircle2,
        badge: 'bg-emerald-500/15 text-emerald-800 dark:text-emerald-300',
        dot: 'bg-emerald-600 dark:bg-emerald-400',
    },
    watch: {
        label: 'À surveiller',
        icon: Eye,
        badge: 'bg-amber-500/15 text-amber-800 dark:text-amber-300',
        dot: 'bg-amber-500 dark:bg-amber-400',
    },
    critical: {
        label: 'Critique',
        icon: AlertOctagon,
        badge: 'bg-red-500/15 text-red-700 dark:text-red-300',
        dot: 'bg-red-600 dark:bg-red-400',
    },
    limited: {
        label: 'Échantillon limité',
        icon: CircleDashed,
        badge: 'bg-muted text-muted-foreground',
        dot: 'bg-muted-foreground/50',
    },
    none: {
        label: 'Aucun courrier échu',
        icon: MinusCircle,
        badge: 'bg-muted text-muted-foreground',
        dot: 'bg-muted-foreground/30',
    },
};

/** Couleur des trois résultats possibles d'un courrier échu. */
export const outcomeColors = {
    on_time: 'bg-emerald-600 dark:bg-emerald-400',
    late: 'bg-amber-500 dark:bg-amber-400',
    overdue: 'bg-red-600 dark:bg-red-400',
} as const;

export const percent = (value: number | null) => (value === null ? '—' : `${value} %`);
export const days = (value: number | null) => (value === null ? '—' : `${value.toString().replace('.', ',')} j`);

const dateFormat = new Intl.DateTimeFormat('fr-SN', { dateStyle: 'medium', timeZone: 'UTC' });
export const formatDate = (value: string) => dateFormat.format(new Date(`${value}T00:00:00Z`));

export const withPeriod = (href: string, period: string) => `${href}?periode=${encodeURIComponent(period)}`;
