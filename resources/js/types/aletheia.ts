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

/** A search queued or running at a place on the map. */
export type MapSearch = {
    id: number;
    status: 'queued' | 'running';
    vector: { id: number; title: string } | null;
    created_at: string | null;
    started_at: string | null;
};

/** A target on the map. Activity is null for members who can't see analyses. */
export type MapPlace = {
    id: number;
    name: string;
    type: Option;
    region: string | null;
    latitude: number | null;
    longitude: number | null;
    votes_count: number;
    running_count: number | null;
    findings_count: number | null;
    max_risk_score: number | null;
    /** When the newest open lead here was found. */
    latest_lead_at: string | null;
    searches: MapSearch[] | null;
    leads: { id: number; title: string; risk_score: number }[] | null;
    /** Vectors with open leads here. */
    patterns: { id: number; title: string }[] | null;
};

/** Two places whose open leads name the same people or organizations. */
export type MapLink = {
    source: number;
    target: number;
    names: string[];
    count: number;
};

/** A search started or an open lead found, for replaying the map over time. */
export type MapTimelineEvent = {
    kind: 'search' | 'lead';
    target_id: number;
    at: string;
    risk_score: number | null;
};

/**
 * The map's replay history: events since `from`, oldest first, and each place's open leads from before
 * `from`, so a replay starts from the right counts.
 */
export type MapHistory = {
    from: string;
    events: MapTimelineEvent[];
    before: { target_id: number; leads: number; top: number }[];
};

/** A search update or new open lead in the map's live feed. */
export type MapEvent = {
    key: string;
    kind: AnalysisStatus | 'lead';
    at: string | null;
    target: { id: number; name: string } | null;
    vector: string | null;
    analysis_id: number;
    finding: { id: number; title: string; risk_score: number } | null;
    findings_count: number | null;
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
