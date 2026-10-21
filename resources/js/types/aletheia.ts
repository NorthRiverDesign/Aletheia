export type Option<T extends string = string> = {
    value: T;
    label: string;
};

export type ProposalStatus = 'proposed' | 'active' | 'rejected' | 'archived';
export type AnalysisStatus = 'queued' | 'running' | 'completed' | 'failed';
export type FindingStatus = 'pending' | 'escalated' | 'dismissed';
export type Verdict =
    | 'supported'
    | 'partly_supported'
    | 'inconclusive'
    | 'unsupported'
    | 'contradicted';
export type ClaimStatus = 'confirmed' | 'unconfirmed' | 'incorrect';

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
    latitude: number | null;
    longitude: number | null;
    status: Option<ProposalStatus>;
    votes_count: number;
    has_voted: boolean;
    creator: string | null;
    created_at: string | null;
};

/** A target on the map. Activity counts are null for members who can't see analyses. */
export type MapPlace = {
    id: number;
    name: string;
    type: string;
    region: string | null;
    latitude: number | null;
    longitude: number | null;
    running_count: number | null;
    findings_count: number | null;
    max_risk_score: number | null;
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
    cooldown_until: string | null;
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
    verification: Verification | null;
    created_at: string | null;
};

export type CheckedClaim = {
    claim: string;
    status: ClaimStatus;
    note: string;
    url: string | null;
};

/** The latest AI fact check of a finding. */
export type Verification = {
    id: number;
    status: Option<AnalysisStatus>;
    verdict: Option<Verdict> | null;
    summary: string | null;
    risk_score: number | null;
    confidence: Option | null;
    claims: CheckedClaim[];
    issues: string[];
    model: string | null;
    error: string | null;
    usage: Record<string, number> | null;
    created_at: string | null;
    completed_at: string | null;
};

export type ReferenceSource = {
    id: number;
    title: string;
    url: string;
    description: string | null;
    origin: Option<'catalog' | 'member' | 'analysis'>;
    verified: boolean;
    analysis_id: number | null;
    last_seen_at: string | null;
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
    manageReferences?: boolean;
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

export type MemberStatus = 'active' | 'banned';

export type Member = {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    email_verified: boolean;
    banned_at: string | null;
    ban_reason: string | null;
    vectors_count: number;
    targets_count: number;
    votes_count: number;
    created_at: string | null;
    can_ban: boolean;
};

export type BannedEmailDomain = {
    id: number;
    domain: string;
    reason: string | null;
    banned_by: string | null;
    created_at: string | null;
};
