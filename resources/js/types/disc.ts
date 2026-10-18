export interface Division {
    id: number;
    code: string;
    name: string;
    is_active?: boolean;
}

export interface CourrierType {
    id: number;
    code: string;
    name: string;
    requires_response: boolean;
    agent_delay_days?: number | null;
    director_delay_days?: number | null;
    is_active?: boolean;
}

export interface Agent {
    id: number;
    name: string;
    division_id?: number | null;
}

export interface CourrierMetrics {
    agent_duration_days: number | null;
    director_duration_days: number | null;
    total_duration_days: number | null;
    due_date: string | null;
    global_gap_days: number | null;
    agent_alert: 'VERT' | 'JAUNE' | 'ROUGE' | null;
    director_alert: 'VERT' | 'JAUNE' | 'ROUGE' | null;
    global_alert: 'VERT' | 'JAUNE' | 'ROUGE' | null;
    current_alert: 'VERT' | 'JAUNE' | 'ROUGE' | null;
    observation: string | null;
}

export interface CourrierEvent {
    id: number;
    event: string;
    from_status: string | null;
    to_status: string | null;
    comment: string | null;
    created_at: string;
    user: { id: number; name: string } | null;
}

export interface CourrierAttachment {
    id: number;
    original_name: string;
    mime_type: string | null;
    size: number;
    created_at: string;
    uploaded_by: { id: number; name: string } | null;
}

export interface Courrier {
    id: number;
    registration_number: string;
    sequence_number: number;
    registration_year: number;
    arrival_date: string;
    reference: string | null;
    origin_service: string;
    subject: string;
    arrival_number: string | null;
    instructions: string | null;
    notes: string | null;
    legacy_agent_name: string | null;
    division_assigned_at: string | null;
    agent_assigned_at: string | null;
    submitted_at: string | null;
    division_validated_at: string | null;
    signed_at: string | null;
    status: string;
    status_label: string;
    agent_delay_days: number | null;
    director_delay_days: number | null;
    type: CourrierType;
    division: Division | null;
    /** Divisions imputées en contribution, en plus de la division pilote. */
    contributing_divisions: Division[];
    assigned_agent: Agent | null;
    /** Agents associés au dossier, en plus de l'agent responsable. */
    co_agents: Agent[];
    division_validated_by: Agent | null;
    metrics: CourrierMetrics;
    events?: CourrierEvent[];
    attachments?: CourrierAttachment[];
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
}
