/**
 * Number and date formatting in the application's language (the <html lang>
 * set by Laravel), not the visitor's browser locale. Without this a browser
 * set to Bengali renders figures as ১,২৮৪ inside an English interface.
 */
function appLocale(): string {
    return (
        (typeof document !== 'undefined' && document.documentElement.lang) ||
        'en'
    );
}

export function formatNumber(
    value: number,
    options?: Intl.NumberFormatOptions,
): string {
    return new Intl.NumberFormat(appLocale(), options).format(value);
}

export function formatDate(
    value: string | Date | null | undefined,
    options: Intl.DateTimeFormatOptions = {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    },
): string {
    if (!value) {
        return '—';
    }

    const date = value instanceof Date ? value : new Date(value);

    return Number.isNaN(date.getTime())
        ? '—'
        : date.toLocaleDateString(appLocale(), options);
}

/** "3 minutes ago", "yesterday", in the application's language. */
export function relativeTime(value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    const seconds = Math.round((new Date(value).getTime() - Date.now()) / 1000);
    const units: [Intl.RelativeTimeFormatUnit, number][] = [
        ['year', 31536000],
        ['month', 2592000],
        ['week', 604800],
        ['day', 86400],
        ['hour', 3600],
        ['minute', 60],
    ];
    const rtf = new Intl.RelativeTimeFormat(appLocale(), { numeric: 'auto' });

    for (const [unit, size] of units) {
        if (Math.abs(seconds) >= size) {
            return rtf.format(Math.round(seconds / size), unit);
        }
    }

    return rtf.format(0, 'second');
}

export function formatDateTime(value: string | null | undefined): string {
    return formatDate(value, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
