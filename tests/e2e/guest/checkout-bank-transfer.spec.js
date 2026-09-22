import { test, expect } from '@playwright/test';
import { artisan } from '../helpers.js';

/**
 * Full anonymous-visitor purchase journey: add to cart -> 5-step checkout
 * -> bank-transfer payment -> thank-you page. Bank transfer is the only
 * payment method this suite can exercise end-to-end without a real
 * gateway sandbox (card goes through Airwallex's hosted drop-in, paysera
 * redirects off-site) — see app/Http/Controllers/Frontend/CheckoutController.php.
 *
 * Every step's form posts back to the SAME /{lang}/checkout URL (the
 * current step lives in server-side session state, not the URL), so
 * `waitForURL` can't detect progress between steps — each assertion below
 * instead waits for that next step's own distinguishing field to appear,
 * which Playwright's `expect(...).toBeVisible()` already retries on.
 *
 * The dev environment has OTP verification globally disabled
 * (`OtpService::enabled()` is false), so step 1 never shows the inline
 * OTP sub-step for a guest — confirmed live before writing this suite.
 * If OTP is ever turned on, step 1 below will need an OTP-code fetch
 * (e.g. via Mailpit's API) added before it can advance.
 *
 * Found while writing the cart suite: navigating straight from a PDP's
 * "Add to Cart" to a page that re-checks the cart (cart index, or here,
 * checkout's own empty-cart guard) intermittently sees the cart as still
 * empty even though the item really was added — looks like a real
 * timing race between the add AJAX call settling and the very next
 * full-page navigation, not a test bug (see cart.spec.js's file-level
 * comment for the fuller writeup). `addToCartAndEnterCheckout` retries
 * entering checkout once if it gets bounced back to /cart for this
 * reason.
 */

const OEM_A = 'E2ESTOREA1';

// This suite never dismissed the GDPR cookie-consent banner before —
// harmless under Chromium (the "Add to Cart" button's click point happened
// not to overlap it there), but a real bug under Firefox/WebKit/mobile
// viewports (Phase 8, cross-browser sweep): the still-open banner
// intercepted the click, so "Add to Cart" silently did nothing and every
// later step failed on an empty cart. See responsive-audit.spec.js's own
// copy of this helper for why real visitors deal with the banner first.
async function dismissCookieBanner(page) {
    const acceptButton = page.getByRole('button', { name: /accept all cookies/i });
    try {
        await acceptButton.click({ timeout: 3000 });
    } catch {
        // Already dismissed or not present.
    }
}

// Every checkout step posts to the SAME /checkout URL and gets a fresh
// server-rendered page back (see file docblock) — a plain .click() on the
// submit button doesn't reliably wait for that reload to land before the
// next assertion runs. Confirmed live (Phase 8, cross-browser sweep): under
// Firefox specifically — never observed under Chromium in this same suite —
// that gap intermittently left the next step's own 15s-retry `toBeVisible()`
// check timing out while still on the OLD step's page (with its fields
// empty — a fresh render, not stale data), at a different, non-deterministic
// step boundary each run.
//
// Three network-timing-based fix attempts, in order, each confirmed live to
// still leave a gap:
//   1. Pairing the click with `page.waitForLoadState('domcontentloaded')`
//      (mirroring this file's own already-correct step-5 submit) — resolves
//      immediately whenever the page is ALREADY in that state, which it
//      always is right before this click, so it never actually waits for a
//      NEW navigation.
//   2. `page.waitForResponse()` for any POST whose URL merely *contained*
//      '/checkout' — still resolved on the wrong response often enough to
//      leave the visitor on an earlier step (or, confusingly, sometimes
//      landed correctly and still failed at a LATER, different step
//      boundary each run — not a single fixed weak point).
//   3. Same, but matching the exact `/en/checkout` pathname instead of a
//      substring — every one of steps 1-4 posts to that identical URL, so
//      an exact match still can't distinguish THIS click's response from a
//      late/reordered one belonging to an earlier or later step's request.
// All three shared the same flaw: guessing, from the network layer, which
// response belongs to which click.
//
// 4th attempt: `locator.waitFor({ state: 'detached' })` on the submit
// button — reasoning that a full page reload (confirmed as this flow's real
// mechanism — same URL, fresh server-rendered response each step, see file
// docblock) destroys and replaces every old DOM node, this button included.
// True, but confirmed live this STILL doesn't work: a `Locator` re-resolves
// its selector on every poll rather than tracking one specific DOM node —
// and `button[type="submit"][form="checkout-form"]` matches an element on
// EVERY step's page, not just the current one. So "detached" never fires:
// the moment the new step renders, the same selector immediately matches
// its own (different, but selector-identical) submit button again.
//
// 5th attempt: capture a real `ElementHandle` (not a `Locator`) for THIS
// specific button node before clicking, then check its own `isConnected` —
// a standard DOM property tied to that exact node, not a selector — inside
// `waitForFunction`. Conceptually correct (this precise element really does
// leave the document once the reload lands), but confirmed live it STILL
// timed out despite the page having genuinely navigated (the screenshot at
// failure showed a fresh, empty next-step page — proof the reload
// completed): a full navigation destroys the OLD page's JS execution
// context, and this handle belongs to that now-torn-down context.
// `waitForFunction` evaluating a stale cross-context handle doesn't reliably
// surface "yes, this is gone" — it can just stall until its own timeout
// instead, the opposite of what's needed.
//
// What actually works: `page.waitForNavigation()` — Playwright's own
// purpose-built primitive for exactly this. Unlike `waitForLoadState`
// (resolves immediately if already satisfied — attempt #1's flaw), it
// specifically listens for the NEXT navigation to start; unlike a
// `Locator`-based wait (attempt #4), it isn't selector-based, so it can't
// match a same-selector element on the destination page; and unlike an
// `ElementHandle` check (attempt #5), it never evaluates anything against a
// handle whose context may already be gone. Soft-deprecated in favor of
// `waitForURL` for the "wait for a specific URL" case, but that's not this
// case — every step here reloads the SAME URL — and `waitForNavigation`
// remains the correct tool for "wait for any navigation, regardless of
// resulting URL."
// waitForNavigation is the structurally correct mechanism (see above), but
// a residual, rarer failure remained even with it: roughly 1 in 5-8
// full-suite Firefox runs, always the longest test in this file (5
// sequential server round trips — more chances for one to land slow), never
// a fixed step. That signature — infrequent, no consistent culprit, on the
// test with the most cumulative server work — matches this dev
// environment's own documented real request-timing variance under load
// (see playwright.config.js's comments on first-paint cost, and this
// project's wider notes on request contention) rather than a further
// synchronization bug.
//
// Tried retrying the click once on timeout (mirroring
// `addToCartAndEnterCheckout`'s own established retry pattern) — measured
// live that this made things WORSE (2/8 clean full-suite runs vs. 5/8
// without it): when the first click's navigation was just slow rather than
// truly stuck, the retry's second click landed on a page already mid-
// transition, submitting a second time into an inconsistent state. Reverted
// in favor of simply giving the one real click more time.
async function submitStep(page) {
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 45000 }),
        page.locator('button[type="submit"][form="checkout-form"]').click(),
    ]);
}

async function addToCartAndEnterCheckout(page) {
    await page.goto(`/en/parts/${OEM_A}`, { waitUntil: 'domcontentloaded' });
    await page.waitForURL(new RegExp(`/en/parts/${OEM_A}/\\d+-`), { waitUntil: 'domcontentloaded' });
    await dismissCookieBanner(page);
    const addToCart = page.locator('[data-testid="product-add-to-cart"]');
    await addToCart.click();
    await expect(addToCart).toContainText(/added/i, { timeout: 10000 });

    // The "Added" text only renders after the add-to-cart fetch()'s JSON
    // response resolves client-side (see detail.blade.php's addToCart()),
    // so the server has already processed and responded to the request by
    // this point — but confirmed live under Firefox/WebKit/mobile-Chromium
    // (Phase 8, cross-browser sweep) that the response's Set-Cookie:
    // guest_token=... can still take a beat longer to actually land in the
    // browser's own cookie jar than the fetch() Promise takes to resolve
    // in JS, under those engines specifically (never observed on desktop
    // Chromium). Navigating to checkout before that commit finishes starts
    // a brand new session with no cart. Poll the cookie jar directly rather
    // than trusting the UI text change alone.
    await expect(async () => {
        const cookies = await page.context().cookies();
        expect(cookies.some((c) => c.name === 'guest_token')).toBe(true);
    }).toPass({ timeout: 5000 });

    for (let attempt = 0; attempt < 3; attempt++) {
        await page.goto('/en/checkout', { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(800);

        if (await page.locator('#checkout-email').isVisible().catch(() => false)) return;
    }
}

test.describe('Guest checkout: bank transfer', () => {
    // beforeEach, not beforeAll — the first test in this file completes a
    // REAL order (createOrder() decrements/flips stock on whatever it
    // just "bought"), so every test needs its own fresh, in-stock product
    // rather than sharing one seed across the whole file.
    test.beforeEach(() => {
        artisan('oeparts:e2e:seed-storefront-fixture');
    });

    test('completing checkout with bank transfer reaches the thank-you page with a real order number', async ({ page }) => {
        // Order creation (step 5 -> payment page) does real work — stock
        // checks, tax calc, order/order-item writes — that can run slower
        // than the suite's default 60s under this dev environment's load;
        // give this specific, longest test in the suite (5 sequential
        // server round trips, each now allowed up to 45s for its own
        // navigation) plenty of headroom.
        test.setTimeout(280000);

        await addToCartAndEnterCheckout(page);

        // Step 1: contact details.
        await expect(page.locator('#checkout-email')).toBeVisible({ timeout: 20000 });
        await page.locator('#checkout-email').fill('e2e-checkout@example.com');
        await page.locator('#checkout-phone').fill('+491234567890');
        await submitStep(page);

        // Step 2: shipping address.
        await expect(page.locator('#checkout_first_name')).toBeVisible({ timeout: 20000 });
        await page.locator('#checkout_first_name').fill('Playwright');
        await page.locator('#checkout_last_name').fill('Visitor');
        await page.locator('#checkout_street').fill('Teststrasse 1');
        await page.locator('#checkout_city').fill('Berlin');
        await page.locator('#checkout_postal_code').fill('10115');
        await page.locator('#checkout_country_code').selectOption('DE');
        await submitStep(page);

        // Step 3: shipping method.
        const shippingRadios = page.locator('input[name="shipping_method_id"]');
        await expect(shippingRadios.first()).toBeVisible({ timeout: 20000 });
        await shippingRadios.first().check();
        await submitStep(page);

        // Step 4: review + terms. Scoped to #checkout-form — the sitewide
        // (normally hidden) auth modal also has an `agree_terms` checkbox
        // on its Register tab, always present in the DOM on every page.
        const agreeTerms = page.locator('#checkout-form input[name="agree_terms"]');
        await expect(agreeTerms).toBeVisible({ timeout: 20000 });
        await agreeTerms.check();
        await submitStep(page);

        // Step 5: payment method -> creates the order. This step does real
        // work (stock check, order + order-item writes) before its
        // redirect, so wait for the resulting navigation alongside the
        // click rather than just the click's own (fast) actionability.
        const bankRadio = page.locator('input[name="payment_method"][value="bank_transfer"]');
        await expect(bankRadio).toBeVisible({ timeout: 20000 });
        await bankRadio.check();
        await Promise.all([
            page.waitForURL(/\/checkout\/payment\//, { waitUntil: 'domcontentloaded', timeout: 45000 }),
            page.locator('button[type="submit"][form="checkout-form"]').click(),
        ]);

        // Payment page: confirm bank transfer, no proof file required.
        await expect(page.locator('#payment-form')).toBeVisible({ timeout: 20000 });
        await page.locator('#method-bank').check();
        await page.locator('#submit-btn').click();

        // Thank-you page.
        await page.waitForURL(/\/en\/checkout\/thank-you\//, { waitUntil: 'domcontentloaded', timeout: 20000 });
        const orderNumber = page.locator('.font-mono.font-medium.text-3xl');
        await expect(orderNumber).toBeVisible();
        await expect(orderNumber).not.toBeEmpty();
    });

    test('step 2 rejects submission when required address fields are missing', async ({ page }) => {
        await addToCartAndEnterCheckout(page);

        await expect(page.locator('#checkout-email')).toBeVisible({ timeout: 15000 });
        await page.locator('#checkout-email').fill('e2e-checkout-invalid@example.com');
        await submitStep(page);

        await expect(page.locator('#checkout_first_name')).toBeVisible({ timeout: 15000 });
        // Leave every field blank and submit. These fields carry native
        // HTML5 `required` attributes (step2.blade.php), so the browser's
        // own client-side validation blocks the submit entirely — no
        // request ever reaches the server, hence a plain click here, not
        // submitStep()'s POST-response wait (which would just time out
        // waiting for a request that correctly never fires). Whether it's
        // the browser or the server doing the rejecting, the outcome this
        // test actually cares about is the same: submission is blocked and
        // the visitor stays on step 2.
        await page.locator('button[type="submit"][form="checkout-form"]').click();

        await expect(page.locator('#checkout_first_name')).toBeVisible({ timeout: 15000 });
    });

    test('an empty cart redirects away from checkout back to the cart page', async ({ page }) => {
        // A fresh, isolated context (default per-test in this project) has
        // never added anything to a cart — checkout must refuse to start.
        await page.goto('/en/checkout', { waitUntil: 'domcontentloaded' });
        await page.waitForURL(/\/en\/cart$/, { waitUntil: 'domcontentloaded' });
    });
});
