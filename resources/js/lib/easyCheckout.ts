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
let currentSessionId = 0;
let activeSessionId = 0;
let activeObserver: MutationObserver | null = null;
let guardianObserver: MutationObserver | null = null;
let guardianTimeout: number | null = null;

function stopCancellationGuardian(): void {
    if (guardianObserver) {
        guardianObserver.disconnect();
        guardianObserver = null;
    }
    if (guardianTimeout !== null) {
        window.clearTimeout(guardianTimeout);
        guardianTimeout = null;
    }
}

/**
 * Temporary guardian observer that runs after cancellation:
 * If SSLCommerz's in-flight network call resolves in the background and
 * attempts to inject an iframe or lock icon into the DOM, this guardian
 * destroys it immediately before it can render or execute.
 */
function startCancellationGuardian(): void {
    stopCancellationGuardian();

    if (
        typeof document === 'undefined' ||
        typeof MutationObserver === 'undefined'
    ) {
        return;
    }

    guardianObserver = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            for (let i = 0; i < mutation.addedNodes.length; i++) {
                const node = mutation.addedNodes[i];
                if (!(node instanceof HTMLElement)) {
                    continue;
                }

                // Destroy any lingering lock icon injected by SSLCommerz
                if (
                    node.classList.contains('icon-lock') ||
                    node.querySelector('.icon-lock')
                ) {
                    node.remove();
                }

                // Destroy any iframe or popup injected by SSLCommerz
                const isIframe = node.tagName === 'IFRAME';
                const iframe = isIframe
                    ? (node as HTMLIFrameElement)
                    : (node.querySelector(
                          'iframe',
                      ) as HTMLIFrameElement | null);

                if (iframe) {
                    const src = (iframe.src || '').toLowerCase();
                    if (
                        src.includes('sslcommerz') ||
                        src.includes('easycheckout') ||
                        !src
                    ) {
                        try {
                            iframe.src = 'about:blank';
                        } catch {
                            // ignore cross-origin error
                        }
                        node.remove();
                        cleanupSslCommerzArtifacts();
                    }
                }

                if (node.classList.contains('mobile_popup')) {
                    node.remove();
                }
            }
        }
    });

    guardianObserver.observe(document.body, { childList: true, subtree: true });

    // Protect for 15 seconds (covers slow in-flight responses)
    guardianTimeout = window.setTimeout(() => {
        stopCancellationGuardian();
    }, 15000);
}

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
 * Completely purges all SSLCommerz DOM artifacts (backdrops, modal containers,
 * headers, iframes, lock icons, spinners) from the document.
 */
export function cleanupSslCommerzArtifacts(): void {
    if (typeof document === 'undefined') {
        return;
    }

    const appRoot = document.getElementById('app');
    const payBtn = document.getElementById(BUTTON_ID);

    const removeTopLevelAncestor = (el: Element | null): void => {
        if (!el) {
            return;
        }
        let top: Element = el;
        while (top.parentElement && top.parentElement !== document.body) {
            top = top.parentElement;
        }
        if (
            top !== document.body &&
            top !== appRoot &&
            top !== payBtn &&
            !top.contains(appRoot) &&
            !top.contains(payBtn)
        ) {
            top.remove();
        } else if (el !== appRoot && el !== payBtn && !el.contains(appRoot)) {
            el.remove();
        }
    };

    // 1. Terminate all network connections on SSLCommerz iframes and remove their top-level trees
    const iframes = document.querySelectorAll<HTMLIFrameElement>(
        'iframe[src*="sslcommerz"], iframe[src*="EasyCheckOut"], iframe[id*="sslcz"], iframe[name*="sslcz"]',
    );
    iframes.forEach((iframe) => {
        try {
            iframe.src = 'about:blank';
        } catch {
            // ignore
        }
        removeTopLevelAncestor(iframe);
    });

    // 2. Remove all modal, backdrop, and popup elements created by SSLCommerz
    const selectors = [
        '.modal-backdrop',
        '.modal:not(#app *)',
        '.modal-dialog:not(#app *)',
        '.mobile_popup',
        '.icon-lock',
        '#sslcz-standalone-backdrop',
        '[id*="sslcz-modal"]',
        '[class*="sslcz-modal"]',
        '[id*="sslcz_"]',
    ];

    selectors.forEach((sel) => {
        document.querySelectorAll(sel).forEach((el) => {
            removeTopLevelAncestor(el);
        });
    });

    // 3. Catch any remaining orphaned logo headers or close buttons created by SSLCommerz
    document.querySelectorAll('body > div').forEach((div) => {
        if (div === appRoot || div.contains(appRoot) || div === payBtn) {
            return;
        }
        const hasLogo = div.querySelector(
            'img[src*="logo" i], img[src*="apple-touch" i], [alt="Logo" i]',
        );
        const hasClose = div.querySelector('.close, [data-dismiss="modal"]');
        const hasLock = div.querySelector('.icon-lock');
        const textHasLogo =
            div.textContent?.includes('Logo') &&
            div.querySelector('button, .close, a');

        if (hasLock || (hasLogo && hasClose) || textHasLogo) {
            div.remove();
        }
    });

    if (document.body) {
        document.body.style.overflow = '';
    }
}

/**
 * Aborts any pending Easy Checkout attempt. If the user closes the loading
 * modal while SSLCommerz is still negotiating in the background, this
 * cancels the session, tears down observers, and starts a guardian observer
 * that prevents any delayed popup from appearing.
 */
export function cancelEasyCheckout(): void {
    activeSessionId = 0;

    if (activeObserver) {
        activeObserver.disconnect();
        activeObserver = null;
    }

    cleanupSslCommerzArtifacts();
    startCancellationGuardian();
}

function observeModal(
    sessionId: number,
    callbacks?: EasyCheckoutCallbacks,
): void {
    if (
        typeof document === 'undefined' ||
        typeof MutationObserver === 'undefined'
    ) {
        return;
    }

    stopCancellationGuardian();
    injectStyles();

    let modalElement: HTMLElement | null = null;

    if (activeObserver) {
        activeObserver.disconnect();
        activeObserver = null;
    }

    const cleanup = () => {
        document.removeEventListener('click', handleGlobalClick, true);
        window.removeEventListener('keydown', handleGlobalKeydown, true);

        if (activeObserver) {
            activeObserver.disconnect();
            activeObserver = null;
        }
        cleanupSslCommerzArtifacts();
        if (activeSessionId === sessionId) {
            callbacks?.onClosed?.();
        }
    };

    const handleGlobalClick = (event: MouseEvent) => {
        const target = event.target as HTMLElement | null;
        if (!target) {
            return;
        }

        // Close button click (e.g. '×' or .close)
        const isCloseBtn = target.closest(
            '.close, [data-dismiss="modal"], [aria-label*="Close" i], .close-button',
        );
        const isCloseChar =
            target.textContent?.trim() === '×' ||
            target.textContent?.trim() === '\u00d7' ||
            target.textContent?.trim() === 'X';
        const inSslModal = target.closest(
            '.modal, .modal-dialog, .modal-content, .mobile_popup, [id*="sslcz"]',
        );

        if (isCloseBtn || (isCloseChar && inSslModal)) {
            cleanup();
            return;
        }

        // Backdrop click outside modal dialog
        if (
            target.classList.contains('modal-backdrop') ||
            (target.classList.contains('modal') &&
                !target.closest('.modal-dialog, .modal-content'))
        ) {
            cleanup();
            return;
        }
    };

    const handleGlobalKeydown = (event: KeyboardEvent) => {
        if (event.key === 'Escape') {
            cleanup();
        }
    };

    document.addEventListener('click', handleGlobalClick, true);
    window.addEventListener('keydown', handleGlobalKeydown, true);

    activeObserver = new MutationObserver((mutations) => {
        // If this session has been cancelled, abort immediately and purge any nodes
        if (activeSessionId !== sessionId) {
            cleanup();
            return;
        }

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
                    if (activeSessionId !== sessionId) {
                        try {
                            iframe.src = 'about:blank';
                        } catch {
                            // ignore
                        }
                        node.remove();
                        cleanup();
                        return;
                    }

                    modalElement = node;
                    callbacks?.onOpened?.();

                    let hasLoaded = false;
                    const handleLoaded = () => {
                        if (hasLoaded || activeSessionId !== sessionId) {
                            return;
                        }
                        hasLoaded = true;
                        window.setTimeout(() => {
                            if (activeSessionId === sessionId) {
                                callbacks?.onLoaded?.();
                            }
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
                    const isIframeRemoved =
                        node.nodeName === 'IFRAME' ||
                        (node instanceof HTMLElement &&
                            node.querySelector('iframe'));
                    if (
                        node === modalElement ||
                        node.contains(modalElement) ||
                        modalElement.contains(node) ||
                        isIframeRemoved
                    ) {
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

    activeObserver.observe(document.body, { childList: true, subtree: true });

    window.setTimeout(() => {
        if (activeObserver) {
            activeObserver.disconnect();
            activeObserver = null;
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

    stopCancellationGuardian();
    cleanupSslCommerzArtifacts();

    const sessionId = ++currentSessionId;
    activeSessionId = sessionId;

    observeModal(sessionId, callbacks);

    el.postdata = { invoice: invoiceId };
    el.token = csrfToken;
    el.click();

    return true;
}
