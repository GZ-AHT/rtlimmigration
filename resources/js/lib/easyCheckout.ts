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

/** Load the script once. Resolves false if it could not be loaded. */
export function loadEasyCheckout(
    scriptUrl: string,
    endpoint: string,
): Promise<boolean> {
    if (loading) {
        return loading;
    }

    if (!button()) {
        const el = document.createElement('button');
        el.id = BUTTON_ID;
        el.type = 'button';
        el.tabIndex = -1;
        el.setAttribute('aria-hidden', 'true');
        // Inline, so the script's own styling for this id cannot show it.
        el.style.display = 'none';
        el.setAttribute('order', 'invoice');
        el.setAttribute('endpoint', endpoint);
        document.body.appendChild(el);
    }

    loading = new Promise((resolve) => {
        const script = document.createElement('script');
        script.src = `${scriptUrl}?${Math.random().toString(36).substring(7)}`;
        script.async = true;
        // Their script sets itself up asynchronously after it arrives.
        script.onload = () => window.setTimeout(() => resolve(true), 600);
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
): boolean {
    const el = button();

    if (!el) {
        return false;
    }

    el.postdata = { invoice: invoiceId };
    el.token = csrfToken;
    el.click();

    return true;
}
