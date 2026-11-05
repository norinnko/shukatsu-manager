export type CompanyStage =
    | 'interested'
    | 'waiting'
    | 'seminar'
    | 'es'
    | 'applied'
    | 'webtest'
    | 'interview'
    | 'final'
    | 'joined'
    | 'offer'
    | 'rejected'
    | 'declined';

export type CompanyPhase = 'intern' | 'early' | 'main';
export type CompanyPriority = 'first' | 'normal' | 'backup';
export type EventType =
    | 'deadline'
    | 'intern'
    | 'interview'
    | 'seminar'
    | 'test'
    | 'other';
export type EventStatus = 'upcoming' | 'done' | 'cancelled';
export type EsCategory =
    | 'self_pr'
    | 'gakuchika'
    | 'motivation'
    | 'skills'
    | 'other';

export type EnumOption<T extends string = string> = {
    value: T;
    label: string;
    color: string | null;
};

export type Enums = {
    stages: EnumOption<CompanyStage>[];
    phases: EnumOption<CompanyPhase>[];
    priorities: EnumOption<CompanyPriority>[];
    eventTypes: EnumOption<EventType>[];
    eventStatuses: EnumOption<EventStatus>[];
    esCategories: EnumOption<EsCategory>[];
    emailStatuses: EnumOption<EmailStatus>[];
    mailCategories: EnumOption<MailCategory>[];
};

export type CompanyRef = { id: number; name: string };

export type EventItem = {
    id: number;
    company?: CompanyRef | null;
    company_id: number | null;
    type: EventType;
    title: string;
    starts_at: string;
    ends_at: string | null;
    due_at: string;
    all_day: boolean;
    location: string | null;
    online_url: string | null;
    memo: string | null;
    status: EventStatus;
    source: 'manual' | 'import' | 'gmail' | 'line';
    sync_google: boolean;
    google_synced: boolean;
    google_sync_error: string | null;
};

export type Company = {
    id: number;
    name: string;
    industry: string | null;
    phase: CompanyPhase;
    stage: CompanyStage;
    priority: CompanyPriority | null;
    tags: string[];
    mypage_url: string | null;
    mypage_login_id: string | null;
    recruit_url: string | null;
    test_type: string | null;
    next_action: string | null;
    result_expected_on: string | null;
    memo: string | null;
    archived: boolean;
    updated_at: string | null;
    next_event?: EventItem | null;
    es_answers_count?: number;
};

export type EsAnswer = {
    id: number;
    company?: CompanyRef | null;
    company_id: number | null;
    category: EsCategory;
    question: string;
    answer: string;
    char_limit: number | null;
    updated_at: string | null;
};

export type Profile = {
    full_name?: string | null;
    full_name_kana?: string | null;
    full_name_roman?: string | null;
    birth_date?: string | null;
    contact_email?: string | null;
    phone?: string | null;
    postal_code?: string | null;
    address?: string | null;
    github_url?: string | null;
    portfolio_url?: string | null;
    note?: string | null;
};

export type Education = {
    id: number;
    school: string;
    faculty: string | null;
    department: string | null;
    major: string | null;
    degree: string | null;
    entered_on: string;
    graduated_on: string | null;
    graduation_status: 'graduated' | 'expected';
};

export type Certification = {
    id: number;
    name: string;
    acquired_on: string | null;
    score: string | null;
};

export type EmailStatus = 'new' | 'done' | 'ignored' | 'excluded';

export type MailCategory = 'schedule' | 'result' | 'task' | 'info' | 'ad' | 'other';

export type MailDecision = 'keep' | 'unsure' | 'exclude';

/** Event details OpenAI read from a mail. */
export type MailEventDraft = {
    type: EventType;
    title: string;
    date: string;
    start_time: string | null;
    end_date: string | null;
    end_time: string | null;
    location: string | null;
    online_url: string | null;
};

/** What a mail asks to do with the calendar; applied only after a click. */
export type MailAction = Omit<MailEventDraft, 'date'> & {
    kind: 'create' | 'update' | 'cancel';
    date: string | null;
    event_id: number | null;
    applied: boolean;
    /** A create whose event is already on the calendar. */
    registered: boolean;
    /** The event an update / cancel refers to, as it is now. */
    current: { title: string; starts_at: string; ends_at: string | null; all_day: boolean; status: EventStatus } | null;
};

export type InboxEmail = {
    id: number;
    company?: CompanyRef | null;
    from_address: string;
    from_name: string | null;
    subject: string;
    snippet: string | null;
    body: string | null;
    received_at: string;
    status: EmailStatus;
    gmail_url: string;
    ai: {
        status: 'pending' | 'done' | 'failed';
        category: MailCategory | null;
        decision: MailDecision | null;
        summary: string | null;
        reason: string | null;
        actions: MailAction[];
        signup_url: string | null;
        error: string | null;
        rule?: { id: number; description: string } | null;
    };
};
