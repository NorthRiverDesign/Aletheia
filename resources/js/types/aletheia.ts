export type Option<T extends string = string> = {
    value: T;
    label: string;
};

export type ProposalStatus = 'proposed' | 'active' | 'rejected' | 'archived';
export type AnalysisStatus = 'queued' | 'running' | 'completed' | 'failed';
export type FindingStatus = 'pending' | 'escalated' | 'dismissed';

export type Vector = {
    id: number;
    title: string;
    category: Option;
    summary: string;
    description: string;
    indicators: string[];
    data_sources: string[];
    status: Option<ProposalStatus>;
    votes_count: number;
    has_voted: boolean;
    creator: string | null;
    created_at: string | null;
};

export type Target = {
    id: number;
    name: string;
    type: Option;
    region: string | null;
    description: string | null;
    website: string | null;
    status: Option<ProposalStatus>;
    votes_count: number;
    has_voted: boolean;
    creator: string | null;
    created_at: string | null;
};

export type Analysis = {
    id: number;
    status: Option<AnalysisStatus>;
    model: string | null;
    summary: string | null;
    error: string | null;
    usage: Record<string, number> | null;
    vector: { id: number; title: string } | null;
    target: { id: number; name: string } | null;
    requester: string | null;
    findings_count: number | null;
    max_risk_score: number | null;
    created_at: string | null;
    started_at: string | null;
    completed_at: string | null;
};

export type Evidence = {
    description: string;
    url: string | null;
};

export type Finding = {
    id: number;
    analysis_id: number;
    title: string;
    summary: string;
    rationale: string;
    risk_score: number;
    confidence: Option;
    entities: string[];
    evidence: Evidence[];
    next_steps: string[];
    status: Option<FindingStatus>;
    review_notes: string | null;
    reviewer: string | null;
    reviewed_at: string | null;
    vector: { id: number; title: string } | null;
    target: { id: number; name: string } | null;
    created_at: string | null;
};

export type PickerOption = {
    id: number;
    label: string;
    votes_count: number;
};

export type ProposalAbilities = {
    update: boolean;
    delete: boolean;
    moderate: boolean;
    vote: boolean;
    analyze: boolean;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
