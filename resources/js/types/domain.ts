/** Shapes of records the server sends to Phase 2 pages. */
import type { Option, Tone } from '@/types/panel';

export type CounselorOption = Option & {
    availability: string;
    availability_label: string;
    open_leads: number;
    capacity: number | null;
    at_capacity: boolean;
};

export type LeadRow = {
    id: number;
    lead_number: string;
    name: string;
    email: string | null;
    phone: string | null;
    country: string | null;
    service: string | null;
    source: string;
    status: string;
    status_label: string;
    status_tone: Tone;
    counselor: string | null;
    next_follow_up_at: string | null;
    follow_up_overdue: boolean;
    is_duplicate: boolean;
    created_at: string | null;
    url?: string;
};

export type StudentRow = {
    id: number;
    student_number: string;
    name: string;
    email: string;
    phone: string | null;
    counselor: string | null;
    source: string;
    account_status: 'active' | 'inactive' | 'deleted';
    email_verified: boolean;
    created_at: string | null;
    url?: string;
};

export type TicketRow = {
    id: number;
    ticket_number: string;
    subject: string;
    requester: string;
    requester_email: string | null;
    category: string | null;
    priority: string;
    priority_label: string;
    priority_tone: Tone;
    status: string;
    status_label: string;
    status_tone: Tone;
    assignee: string | null;
    last_reply_at: string | null;
    created_at: string | null;
    updated_at: string | null;
};

export type DocumentItem = {
    id: number;
    name: string;
    original_name: string;
    size: number;
    mime_type: string;
    status: 'pending' | 'verified' | 'rejected';
    status_label: string;
    status_tone: Tone;
    rejection_reason: string | null;
    file_number: string | null;
    expires_at: string | null;
    uploaded_by: string | null;
    uploaded_at: string | null;
    reviewed_by: string | null;
    reviewed_at: string | null;
    can: { review: boolean; delete: boolean };
    view_url: string;
    download_url: string;
    review_url?: string;
    delete_url?: string;
};

export type ChecklistItem = {
    category_id: number | null;
    category: string;
    description: string | null;
    required: boolean;
    document: DocumentItem | null;
    history: {
        id: number;
        original_name: string;
        status_label: string;
        uploaded_at: string | null;
        uploaded_by: string | null;
        view_url?: string;
    }[];
};

export type Checklist = {
    items: ChecklistItem[];
    required: number;
    verified: number;
};

export type UploadLimits = { max_mb: number; extensions: string[] };

export type Completeness = { percent: number; missing: string[] };

export type FileRow = {
    id: number;
    file_number: string;
    student: string | null;
    student_number: string | null;
    service: string | null;
    country: string | null;
    intake: string | null;
    status: string;
    status_label: string;
    status_tone: Tone;
    opened_at: string;
    closed_at: string | null;
    url?: string;
};

export type EducationRow = {
    id?: number | null;
    level: string;
    institution: string;
    board_or_university: string | null;
    country: string | null;
    major: string | null;
    result: string | null;
    passing_year: number | string | null;
};

export type ProfileForm = Record<string, string | null> & {
    educations: EducationRow[];
};

export type PaymentRequestRow = {
    id: number;
    invoice_number: string;
    type: string;
    description: string | null;
    student: string | null;
    student_number: string | null;
    file_number: string | null;
    amount: number;
    amount_label: string;
    paid_label: string;
    outstanding: number;
    outstanding_label: string;
    currency: string;
    due_date: string | null;
    overdue: boolean;
    status: string;
    status_label: string;
    status_tone: Tone;
    requested_by: string | null;
    created_at: string | null;
    url?: string;
};

export type PaymentRow = {
    id: number;
    invoice_number: string | null;
    type: string | null;
    student: string | null;
    student_number: string | null;
    amount: number;
    amount_label: string;
    method: string;
    transaction_reference: string | null;
    paid_on: string;
    has_evidence: boolean;
    evidence_name: string | null;
    status: 'pending' | 'verified' | 'confirmed' | 'rejected';
    status_label: string;
    status_tone: Tone;
    rejection_reason: string | null;
    receipt_number: string | null;
    submitted_by: string | null;
    verified_by: string | null;
    confirmed_by: string | null;
    confirmed_at: string | null;
    notes: string | null;
    created_at: string | null;
    url?: string | null;
    evidence_url?: string | null;
};

export type PaymentMethodOption = Option & {
    requires_reference: boolean;
    requires_evidence: boolean;
    instructions: string | null;
};

export type HistoryEntry = {
    id: number;
    from: string | null;
    to: string;
    by: string | null;
    note: string | null;
    at: string;
};

export type MoneyTotals = {
    total: string;
    paid: string;
    due: string;
    due_minor: number;
    next: {
        type: string;
        invoice_number: string;
        amount: string;
        due_date: string | null;
    } | null;
};
