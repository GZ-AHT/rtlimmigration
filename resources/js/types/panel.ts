/** Types shared by the four dashboard areas (admin, counselor, accounts, portal). */

export type AreaKey = 'admin' | 'counselor' | 'accounts' | 'portal';

export type CurrentArea = {
    key: AreaKey;
    label: string;
};

export type PanelNavLeaf = {
    title: string;
    /** Material Symbols icon name. */
    icon: string | null;
    /** Null while the module is planned but not built yet. */
    href: string | null;
    phase: number;
    planned: boolean;
};

export type PanelNavItem = PanelNavLeaf & {
    icon: string;
    /** Sub-menu entries; empty for a plain link. */
    children: PanelNavLeaf[];
};

export type PanelNavGroup = {
    label: string;
    items: PanelNavItem[];
};

export type Tone = 'success' | 'warning' | 'danger' | 'info' | 'neutral';

export type Stat = {
    key: string;
    label: string;
    value: number | string;
    icon: string;
    hint?: string;
};

/** Laravel LengthAwarePaginator JSON shape. */
export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

export type TableColumn<Row = Record<string, unknown>> = {
    key: string;
    label: string;
    sortable?: boolean;
    align?: 'left' | 'right' | 'center';
    /** Hide below this breakpoint to keep tables readable on phones. */
    hideBelow?: 'sm' | 'md' | 'lg';
    class?: string;
    format?: (row: Row) => string;
};

export type SortDirection = 'asc' | 'desc';

export type Option = { value: string; label: string };

export type AppNotification = {
    id: string;
    title: string;
    body: string;
    icon: string;
    category: string;
    has_link: boolean;
    read: boolean;
    created_at: string | null;
};

export type ActivityEntry = {
    id: number;
    log: string | null;
    event: string | null;
    summary: string;
    subject: string | null;
    causer: string | null;
    created_at: string | null;
};

export type SearchResult = {
    title: string;
    subtitle: string | null;
    url: string;
    icon: string;
};

export type SearchGroup = {
    group: string;
    results: SearchResult[];
};
