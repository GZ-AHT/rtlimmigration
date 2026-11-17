/**
 * Cloudflare Turnstile, loaded only when the server asks for a check. The
 * token it returns is verified on the server; the browser never decides.
 */

type TurnstileApi = {
    render: (
        el: HTMLElement,
        options: {
            sitekey: string;
            callback: (token: string) => void;
            'error-callback'?: () => void;
            'expired-callback'?: () => void;
            theme?: 'auto' | 'light' | 'dark';
        },
    ) => string;
    reset: (id?: string) => void;
    remove: (id: string) => void;
};

declare global {
    interface Window {
        turnstile?: TurnstileApi;
    }
}

const SCRIPT =
    'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';

let loading: Promise<TurnstileApi> | null = null;

export function loadTurnstile(): Promise<TurnstileApi> {
    if (window.turnstile) {
        return Promise.resolve(window.turnstile);
    }

    loading ??= new Promise<TurnstileApi>((resolve, reject) => {
        const script = document.createElement('script');
        script.src = SCRIPT;
        script.async = true;
        script.defer = true;
        script.onload = () =>
            window.turnstile
                ? resolve(window.turnstile)
                : reject(new Error('Turnstile did not load.'));
        script.onerror = () => {
            loading = null;
            reject(new Error('Turnstile could not be loaded.'));
        };
        document.head.appendChild(script);
    });

    return loading;
}

/** Renders a widget into `el`; `onToken` receives each new token (null when it expires or fails). */
export async function renderTurnstile(
    el: HTMLElement,
    siteKey: string,
    onToken: (token: string | null) => void,
): Promise<() => void> {
    const api = await loadTurnstile();
    const id = api.render(el, {
        sitekey: siteKey,
        theme: 'light',
        callback: (token) => onToken(token),
        'error-callback': () => onToken(null),
        'expired-callback': () => onToken(null),
    });

    return () => api.remove(id);
}
