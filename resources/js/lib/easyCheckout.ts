/**
 * SSLCommerz Easy Checkout (their embed.min.js): the gateway opens in a
 * popup over our page instead of on a separate page.
 *
 * How their script works, which shapes this file:
 *  - when it loads it reads `order` and `endpoint` from the element with id
 *    "sslczPayBtn", once, and only then starts listening for clicks on it.
 *    So the button must exist first, and there is one endpoint per page;
 *  - on each click it reads the button's `postdata` and `token` properties
 *    and posts them to that endpoint as `cart_json` and `_token`. We say
 *    which invoice to pay through `postdata`;
 *  - it keeps a reference to the button, so the button lives on <body>
 *    for the whole visit rather than inside a page component.
 *
 * Paying this way proves nothing by itself: the server confirms every
 * payment with the gateway before recording it.
 */

type EasyButton = HTMLButtonElement & { postdata?: unknown; token?: string };

export type EasyCheckoutCallbacks = {
    onOpened?: () => void;
    onLoaded?: () => void;
    onClosed?: () => void;
};

const BUTTON_ID = 'sslczPayBtn';
let loading: Promise<boolean> | null = null;

function button(): EasyButton | null {
    return document.getElementById(BUTTON_ID) as EasyButton | null;
}

/**
 * On phones their script opens a new tab after a network call, which
 * mobile browsers commonly block. Phones use the gateway's own page.
 */
export function easyCheckoutSupported(): boolean {
    return !/Android|iPhone|iPad|iPod|Mobile|Opera Mini|IEMobile/i.test(
        navigator.userAgent,
    );
}

function injectStyles(): void {
    if (
        typeof document === 'undefined' ||
        document.getElementById('sslcz-helper-styles')
    ) {
        return;
    }
    const style = document.createElement('style');
    style.id = 'sslcz-helper-styles';
    style.textContent = `
        @keyframes sslcz-spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .sslcz-standalone-backdrop {
            position: fixed;
            inset: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(15, 23, 42, 0.45);
            backdrop-filter: blur(2px);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            z-index: 99998;
            pointer-events: auto;
            transition: opacity 0.25s ease-out;
        }
        .sslcz-standalone-card {
            width: 380px;
            max-width: 92vw;
            height: 560px;
            max-height: 90vh;
            background: #ffffff;
            border-radius: 6px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px;
            box-sizing: border-box;
            position: relative;
            overflow: hidden;
        }
        .sslcz-spinner {
            width: 36px;
            height: 36px;
            border: 3.5px solid #e2e8f0;
            border-top-color: #0284c7;
            border-radius: 50%;
            animation: sslcz-spin 0.8s linear infinite;
        }
    `;
    document.head.appendChild(style);
}

/**
 * Removes any lingering .icon-lock, SSLCommerz loading wrappers, or
 * leftover modal frames from the DOM.
 */
export function cleanupSslCommerzArtifacts(): void {
    if (typeof document === 'undefined') {
        return;
    }

    // Remove any lingering lock icons from SSLCommerz
    document.querySelectorAll('.icon-lock').forEach((el) => el.remove());

    // Remove any lingering standalone loaders or wrappers
    const standalone = document.getElementById('sslcz-standalone-backdrop');
    if (standalone) {
        standalone.remove();
    }
}

function observeModal(callbacks?: EasyCheckoutCallbacks): void {
    if (
        typeof document === 'undefined' ||
        typeof MutationObserver === 'undefined'
    ) {
        return;
    }

    injectStyles();

    let modalElement: HTMLElement | null = null;
    let observer: MutationObserver | null = null;

    const cleanup = () => {
        if (observer) {
            observer.disconnect();
            observer = null;
        }
        cleanupSslCommerzArtifacts();
        callbacks?.onClosed?.();
    };

    observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            for (let i = 0; i < mutation.addedNodes.length; i++) {
                const node = mutation.addedNodes[i];
                if (!(node instanceof HTMLElement)) {
                    continue;
                }

                const iframe =
                    node.tagName === 'IFRAME'
                        ? (node as HTMLIFrameElement)
                        : (node.querySelector(
                              'iframe',
                          ) as HTMLIFrameElement | null);

                if (iframe) {
                    modalElement = node;
                    callbacks?.onOpened?.();

                    let hasLoaded = false;
                    const handleLoaded = () => {
                        if (hasLoaded) {
                            return;
                        }
                        hasLoaded = true;
                        window.setTimeout(() => {
                            callbacks?.onLoaded?.();
                        }, 250);
                    };

                    iframe.addEventListener('load', handleLoaded, {
                        once: true,
                    });

                    // In case iframe has already loaded
                    try {
                        if (
                            iframe.contentDocument &&
                            iframe.contentDocument.readyState === 'complete' &&
                            iframe.contentDocument.body &&
                            iframe.contentDocument.body.children.length > 0
                        ) {
                            handleLoaded();
                        }
                    } catch {
                        // Cross-origin access may throw, load event listener handles it
                    }

                    // Safety timeout
                    window.setTimeout(handleLoaded, 10000);
                }
            }

            if (modalElement) {
                for (let i = 0; i < mutation.removedNodes.length; i++) {
                    const node = mutation.removedNodes[i];
                    if (node === modalElement || node.contains(modalElement)) {
                        modalElement = null;
                        cleanup();
                        return;
                    }
                }
            } else {
                // If .icon-lock is injected when no modal is active, remove it immediately
                const floatingLock =
                    document.querySelector('body > .icon-lock');
                if (floatingLock) {
                    floatingLock.remove();
                }
            }
        }
    });

    observer.observe(document.body, { childList: true, subtree: true });

    window.setTimeout(() => {
        if (observer) {
            observer.disconnect();
            observer = null;
        }
        cleanupSslCommerzArtifacts();
    }, 300000);
}

/** Load the script once. Resolves false if it could not be loaded. */
export function loadEasyCheckout(
    scriptUrl: string,
    endpoint: string,
): Promise<boolean> {
    const existing = button();
    if (existing) {
        existing.setAttribute('endpoint', endpoint);
    } else if (typeof document !== 'undefined') {
        const el = document.createElement('button');
        el.id = BUTTON_ID;
        el.type = 'button';
        el.tabIndex = -1;
        el.setAttribute('aria-hidden', 'true');
        el.style.display = 'none';
        el.setAttribute('order', 'invoice');
        el.setAttribute('endpoint', endpoint);
        document.body.appendChild(el);
    }

    if (loading) {
        return loading;
    }

    loading = new Promise((resolve) => {
        if (typeof document === 'undefined') {
            resolve(false);
            return;
        }

        const script = document.createElement('script');
        script.src = scriptUrl;
        script.async = true;

        script.onload = () => {
            window.setTimeout(() => resolve(true), 50);
        };
        script.onerror = () => {
            loading = null;
            resolve(false);
        };
        document.body.appendChild(script);
    });

    return loading;
}

/** Open the popup for one invoice. Returns false if it cannot be opened. */
export function openEasyCheckout(
    invoiceId: number,
    csrfToken: string,
    callbacks?: EasyCheckoutCallbacks,
): boolean {
    const el = button();

    if (!el) {
        return false;
    }

    observeModal(callbacks);

    el.postdata = { invoice: invoiceId };
    el.token = csrfToken;
    el.click();

    return true;
}
