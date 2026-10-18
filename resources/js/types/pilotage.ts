/** good : objectif atteint · watch : sous l'objectif · critical : sous le seuil d'alerte · limited : échantillon trop faible · none : rien d'échu */
export type Standing = 'good' | 'watch' | 'critical' | 'limited' | 'none';

export interface Period {
    key: string;
    year: number;
    quarter: number;
    label: string;
    months: string;
    start_date: string;
    end_date: string;
    is_current: boolean;
}

export interface PeriodOption {
    key: string;
    label: string;
    is_current: boolean;
}

export interface Thresholds {
    target: number;
    alert: number;
    min_sample: number;
    significant_change: number;
}

export interface Score {
    on_time: number;
    late: number;
    overdue: number;
    not_due: number;
    measured: number;
    rate: number | null;
    standing: Standing;
    previous_rate: number | null;
    previous_measured: number;
    delta: number | null;
    significant_change: boolean;
}

export interface StageScore extends Score {
    key: 'drafting' | 'signature' | 'global';
    label: string;
}

export interface Activity {
    received: number;
    produced: number;
    signed: number;
    backlog: number;
    backlog_date: string;
    median_drafting_days: number | null;
    correction_rate: number | null;
}

export interface QuarterCell {
    key: string;
    label: string;
    months: string;
    is_current: boolean;
    is_future: boolean;
    rate: number | null;
    measured: number;
    standing: Standing;
}

export interface LateItem {
    id: number;
    registration_number: string;
    subject: string;
    can_open: boolean;
    division: string | null;
    agent: string | null;
    deadline: string;
    done_at: string | null;
    days_late: number;
    outcome: 'late' | 'overdue';
}

export interface LateList {
    total: number;
    overdue: number;
    items: LateItem[];
}

export interface Highlight {
    tone: 'good' | 'watch' | 'critical' | 'info';
    text: string;
}

export interface DivisionRow {
    id: number;
    code: string;
    name: string;
    drafting: Score;
    global_rate: number | null;
    global_measured: number;
    received: number;
    backlog: number;
}

export interface AgentRow {
    id: number;
    name: string;
    division: string | null;
    is_active: boolean;
    can_open: boolean;
    drafting: Score;
    received: number;
    produced: number;
    in_hand: number;
    median_drafting_days: number | null;
}

export interface Scorecard {
    scores: StageScore[];
    activity: Activity;
    quarters: QuarterCell[];
    late: LateList;
    highlights: Highlight[];
}
