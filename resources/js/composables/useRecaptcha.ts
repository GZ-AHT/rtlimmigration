import { usePage } from '@inertiajs/vue3';

type Grecaptcha = {
    ready: (cb: () => void) => void;
    execute: (key: string, options: { action: string }) => Promise<string>;
};

declare global {
    interface Window {
        grecaptcha?: Grecaptcha;
    }
}

let loading: Promise<void> | null = null;

function load(siteKey: string): Promise<void> {
    loading ??= new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = `https://www.google.com/recaptcha/api.js?render=${encodeURIComponent(siteKey)}`;
        script.async = true;
        script.onload = () => window.grecaptcha?.ready(() => resolve());
        script.onerror = () => {
            loading = null;
            reject(new Error('reCAPTCHA failed to load'));
        };
        document.head.appendChild(script);
    });

    return loading;
}

/**
 * Google reCAPTCHA v3 for website forms (System → Settings → Integrations).
 * `token(action)` resolves to a token to send as `recaptcha_token`, or an
 * empty string when reCAPTCHA is off or couldn't load; the server decides
 * what to do with a missing token.
 */
export function useRecaptcha() {
    const page = usePage();

    async function token(action: string): Promise<string> {
        const integrations = (
            page.props.site as Record<string, Record<string, unknown>>
        )?.integrations;
        const siteKey = integrations?.recaptcha_site_key;

        if (
            integrations?.recaptcha_enabled !== true ||
            typeof siteKey !== 'string' ||
            !siteKey
        ) {
            return '';
        }

        try {
            await load(siteKey);

            return (
                (await window.grecaptcha?.execute(siteKey, { action })) ?? ''
            );
        } catch {
            return '';
        }
    }

    return { token };
}
