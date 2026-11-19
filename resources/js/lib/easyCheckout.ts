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
 *  - it creates its modal popup using tingle.js (.tingle-modal) and an overlay (.shaz_back_overlay);
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

export function isEasyCheckoutActive(): boolean {
    return activeSessionId !== 0;
}

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
 * attempts to inject tingle modals, shaz overlays, iframes or lock icons into the DOM,
 * this guardian destroys them immediately before they can render or execute.
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

                // If any SSLCommerz loading, lock, overlay, logo or modal element is added
                const isSslElement =
                    node.classList.contains('tingle-modal') ||
                    node.classList.contains('tingle-modal-box') ||
                    node.classList.contains('shaz_back_overlay') ||
                    node.classList.contains('client_logo') ||
                    node.classList.contains('client_logo_inner') ||
                    node.classList.contains('icon-lock') ||
                    node.classList.contains('sloader') ||
                    node.classList.contains('lock-center') ||
                    node.classList.contains('mobile_popup') ||
                    node.classList.contains('swal2-container') ||
                    Boolean(
                        node.querySelector(
                            '.tingle-modal, .tingle-modal-box, .shaz_back_overlay, .client_logo, .icon-lock, .sloader, .lock-center',
                        ),
                    );

                const isIframe = node.tagName === 'IFRAME';
                const iframe = isIframe
                    ? (node as HTMLIFrameElement)
                    : (node.querySelector(
                          'iframe',
                      ) as HTMLIFrameElement | null);

                let isSslIframe = false;
                if (iframe) {
                    const src = (iframe.src || '').toLowerCase();
                    const name = (iframe.name || '').toLowerCase();
                    if (
                        src.includes('sslcommerz') ||
                        src.includes('easycheckout') ||
                        name === 'frame' ||
                        !src
                    ) {
                        isSslIframe = true;
                        try {
                            iframe.src = 'about:blank';
                        } catch {
                            // ignore cross-origin error
                        }
                    }
                }

                if (isSslElement || isSslIframe) {
                    node.remove();
                    cleanupSslCommerzArtifacts();
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

/**
 * Completely purges all SSLCommerz DOM artifacts (tingle modals, shaz backdrops,
 * client logos, headers, iframes, lock icons, spinners) from the document.
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
        'iframe[src*="sslcommerz" i], iframe[src*="easycheckout" i], iframe[name="frame"], iframe[id*="sslcz" i], iframe[name*="sslcz" i]',
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
        '.tingle-modal',
        '.tingle-modal-box',
        '.tingle-modal__close',
        '.shaz_back_overlay',
        '.client_logo',
        '.client_logo_inner',
        '.iframeWrap',
        '.icon-lock',
        '.lock-center',
        '.sloader',
        '.modal-backdrop',
        '.modal:not(#app *)',
        '.modal-dialog:not(#app *)',
        '.mobile_popup',
        '.swal2-container',
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
        const hasTingle =
            div.classList.contains('tingle-modal') ||
            div.querySelector('.tingle-modal, .tingle-modal-box');
        const hasShaz =
            div.classList.contains('shaz_back_overlay') ||
            div.querySelector('.shaz_back_overlay');
        const hasLogo = div.querySelector(
            '.client_logo, img[src*="logo" i], img[src*="apple-touch" i], [alt="Logo" i]',
        );
        const hasClose = div.querySelector(
            '.close, .tingle-modal__close, [data-dismiss="modal"]',
        );
        const hasLock = div.querySelector('.icon-lock, .sloader, .lock-center');
        const textHasLogo =
            div.textContent?.includes('Logo') &&
            div.querySelector('button, .close, a, .tingle-modal__close');

        if (
            hasTingle ||
            hasShaz ||
            hasLock ||
            (hasLogo && hasClose) ||
            textHasLogo
        ) {
            div.remove();
        }
    });

    // 4. Remove any body classes and inline styles added by SSLCommerz or tingle
    if (document.body) {
        document.body.classList.remove(
            'tingle-enabled',
            'pop_up_mobile_device',
            'modal-open',
            'swal2-shown',
            'swal2-height-auto',
        );
        document.body.style.overflow = '';
        document.body.style.position = '';
        document.body.style.top = '';
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
        startCancellationGuardian();
        if (activeSessionId === sessionId) {
            callbacks?.onClosed?.();
        }
    };

    const handleGlobalClick = (event: MouseEvent) => {
        const target = event.target as HTMLElement | null;
        if (!target) {
            return;
        }

        // Close button click (tingle close button, '×', .close, etc.)
        const isCloseBtn = Boolean(
            target.closest(
                '.tingle-modal__close, .tingle-modal__closeIcon, .tingle-modal__closeLabel, .close, [data-dismiss="modal"], [aria-label*="Close" i], .close-button',
            ),
        );
        const isCloseChar =
            target.textContent?.trim() === '×' ||
            target.textContent?.trim() === '\u00d7' ||
            target.textContent?.trim() === 'X';
        const inSslModal = Boolean(
            target.closest(
                '.tingle-modal, .tingle-modal-box, .shaz_back_overlay, .client_logo, .modal, .modal-dialog, .modal-content, .mobile_popup, [id*="sslcz"]',
            ),
        );

        if (isCloseBtn || (isCloseChar && inSslModal)) {
            event.preventDefault();
            event.stopPropagation();
            cleanup();
            return;
        }

        // Backdrop click outside modal dialog / card
        const isBackdropClick =
            target.classList.contains('tingle-modal') ||
            target.classList.contains('shaz_back_overlay') ||
            target.classList.contains('modal-backdrop') ||
            (Boolean(target.closest('.tingle-modal')) &&
                !target.closest('.tingle-modal-box')) ||
            (target.classList.contains('modal') &&
                !target.closest('.modal-dialog, .modal-content'));

        if (isBackdropClick) {
            event.preventDefault();
            event.stopPropagation();
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
            // Check attribute changes on tingle modal (e.g. display: none or class removed)
            if (mutation.type === 'attributes') {
                const target = mutation.target as HTMLElement | null;
                if (target && target.classList.contains('tingle-modal')) {
                    if (
                        !target.classList.contains('tingle-modal--visible') ||
                        target.style.display === 'none'
                    ) {
                        cleanup();
                        return;
                    }
                }
            }

            for (let i = 0; i < mutation.addedNodes.length; i++) {
                const node = mutation.addedNodes[i];
                if (!(node instanceof HTMLElement)) {
                    continue;
                }

                // Instantly remove any injected lock icon or shaz overlay
                if (
                    node.classList.contains('icon-lock') ||
                    node.classList.contains('shaz_back_overlay') ||
                    node.classList.contains('sloader') ||
                    node.querySelector(
                        '.icon-lock, .shaz_back_overlay, .sloader',
                    )
                ) {
                    node.remove();
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

                    modalElement =
                        node.closest('.tingle-modal') || (node as HTMLElement);
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
            }
        }
    });

    activeObserver.observe(document.body, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['class', 'style'],
    });

    // Max 5 minutes safety timeout
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
