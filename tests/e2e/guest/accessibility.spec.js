import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { artisan } from '../helpers.js';

/**
 * Phase 6 (Accessibility, WCAG 2.1 AA) — storefront sweep. Same fixture
 * commands and page selection philosophy as responsive-audit.spec.js
 * (representative page shapes, not every route): axe-core's ruleset already
 * covers automated checks for the bulk of WCAG 2.1 A/AA success criteria
 * (color contrast, alt text, form labels, landmark structure, ARIA
 * correctness, etc.) in one pass per page — no need to hand-write a check
 * per criterion.
 *
 * axe-core only catches what's mechanically detectable — it can't judge
 * whether a tab order "makes sense" or alt text is *meaningful* rather than
 * just *present*. The keyboard-navigation and zoom-reflow tests below cover
 * the manual-judgment gaps axe structurally can't.
 */

const FIXTURE_QUERY_MULTI = 'E2EGUEST';
const FIXTURE_MANUFACTURER_SLUG = 'e2e-storefront-fixture';
const CART_OEM_A = 'E2ESTOREA1';

async function dismissCookieBanner(page) {
    const acceptButton = page.getByRole('button', { name: /accept all cookies/i });
    try {
        await acceptButton.click({ timeout: 3000 });
    } catch {
        // Already dismissed or not present.
    }
    // Clicking the banner leaves Chromium's real synthetic pointer resting
    // at that screen position — if page content reflows into that exact
    // spot afterward (confirmed live: the "Popular OEM part numbers" grid
    // on /en/parts does), that element is now genuinely :hover-active for
    // every check that follows, which color-contrast scans read as real
    // page state (it is), not a scan artifact — but it's not what a real
    // visitor's resting cursor position looks like either. Park the pointer
    // somewhere neutral so scans see the same at-rest state a fresh visitor
    // would.
    await page.mouse.move(0, 0);
}

/**
 * WCAG tags only — 'best-practice' rules are real axe-core rules but aren't
 * WCAG success criteria themselves (e.g. "region", "landmark-unique"), so
 * failing on them would conflate "not WCAG 2.1 AA compliant" with "doesn't
 * match axe's house style". This phase is scoped to the former.
 */
const WCAG_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'];

// bp-rise entrance animations (resources/css/app.css) run up to 0.7s with up
// to a 0.5s stagger delay (.bp-rise-delay-5) — 1.2s worst case before an
// element's final opacity/color settles. A shorter wait here previously
// caught several elements mid-fade and produced spurious color-contrast
// "violations" for a transient frame no real visitor ever perceives as the
// page's resting state.
const ANIMATION_SETTLE_MS = 1500;

async function scanPage(page, path, expectedStatus = 200) {
    const response = await page.goto(path, { waitUntil: 'domcontentloaded' });

    // An axe scan of the WRONG page passes just as happily as a scan of the
    // right one — a 404 error page has no WCAG violations. Confirmed live
    // 2026-09-24: with the default "en" Language row deleted, every /en/...
    // URL 404'd and this file's homepage/search/brands/blog/cart/contact
    // scans all reported green while scanning nothing but error pages. Assert
    // the page actually is what its label claims before trusting the scan.
    expect(
        response?.status(),
        `${path} returned HTTP ${response?.status()} but ${expectedStatus} was expected — a WCAG scan of an unintended error page would pass for the wrong reason`,
    ).toBe(expectedStatus);

    await page.waitForTimeout(ANIMATION_SETTLE_MS);
    await dismissCookieBanner(page);

    return new AxeBuilder({ page }).withTags(WCAG_TAGS).analyze();
}

/**
 * Real checkout step 1 requires an active checkout session
 * (CheckoutService::start(), kicked off server-side when the cart page's
 * own "proceed to checkout" link is followed) — navigating straight to
 * /en/checkout without one redirects back to /en/cart. A prior version of
 * this suite did exactly that direct navigation and its scans passed, but
 * for the wrong reason: it was silently scanning the cart page relabeled
 * "checkout step 1", not real checkout step 1 at all.
 */
async function goToCheckoutStep1(page) {
    await page.goto(`/en/parts/${CART_OEM_A}`, { waitUntil: 'domcontentloaded' });
    await page.waitForURL(new RegExp(`/en/parts/${CART_OEM_A}/\\d+-`), { waitUntil: 'domcontentloaded' });
    await dismissCookieBanner(page);
    await page.getByRole('button', { name: /add to cart/i }).first().click();
    await page.waitForTimeout(800);

    await page.goto('/en/cart', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(400);
    if (await page.getByText(/cart is empty/i).count() > 0) {
        // Same read-after-write race documented in responsive-audit.spec.js
        // and cart.spec.js.
        await page.reload({ waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(400);
    }

    await page.locator('main a[href$="/checkout"]').first().click();
    await page.waitForURL(/\/en\/checkout$/, { waitUntil: 'domcontentloaded' });
}

function formatViolations(results) {
    return results.violations
        .map((v) => `[${v.impact}] ${v.id}: ${v.help} (${v.nodes.length} node(s)) — ${v.nodes.slice(0, 3).map((n) => n.target.join(' ')).join(' | ')}`)
        .join('\n');
}

const PAGES = [
    { label: 'homepage', path: '/en/' },
    { label: 'search-console', path: '/en/parts' },
    { label: 'search-results-multi', path: `/en/parts/${FIXTURE_QUERY_MULTI}` },
    // An unknown OEM legitimately answers 404 (with a search-again page) by design.
    { label: 'zero-results', path: '/en/parts/NOSUCHOEMXYZ999NOPE', status: 404 },
    { label: 'brands-index', path: '/en/brands' },
    { label: 'manufacturer-show', path: `/en/brand/${FIXTURE_MANUFACTURER_SLUG}` },
    { label: 'blog-index', path: '/en/blog' },
    { label: 'cart-empty', path: '/en/cart' },
    { label: 'contact', path: '/en/contact' },
    { label: 'error-404', path: '/en/this-page-does-not-exist-xyz-audit', status: 404 },
];

test.describe('Storefront accessibility (WCAG 2.1 A/AA)', () => {
    test.beforeAll(() => {
        artisan('oeparts:e2e:seed-guest-fixture');
        artisan('oeparts:e2e:seed-storefront-fixture');
    });

    test.afterAll(() => {
        artisan('oeparts:e2e:seed-guest-fixture --cleanup');
        artisan('oeparts:e2e:seed-storefront-fixture --cleanup');
    });

    for (const pg of PAGES) {
        test(`${pg.label}: no WCAG 2.1 A/AA violations`, async ({ page }) => {
            const results = await scanPage(page, pg.path, pg.status);
            expect(results.violations, formatViolations(results)).toEqual([]);
        });
    }

    test('product-detail: no WCAG 2.1 A/AA violations', async ({ page }) => {
        await page.goto(`/en/parts/${CART_OEM_A}`, { waitUntil: 'domcontentloaded' });
        await page.waitForURL(new RegExp(`/en/parts/${CART_OEM_A}/\\d+-`), { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(ANIMATION_SETTLE_MS);
        await dismissCookieBanner(page);

        const results = await new AxeBuilder({ page }).withTags(WCAG_TAGS).analyze();
        expect(results.violations, formatViolations(results)).toEqual([]);
    });

    test('checkout (step 1): no WCAG 2.1 A/AA violations', async ({ page }) => {
        await goToCheckoutStep1(page);
        await page.waitForTimeout(ANIMATION_SETTLE_MS);

        const results = await new AxeBuilder({ page }).withTags(WCAG_TAGS).analyze();
        expect(results.violations, formatViolations(results)).toEqual([]);
    });
});

test.describe('Keyboard-only navigation', () => {
    /**
     * axe-core can flag a missing/zero tabindex or an ARIA misuse, but can't
     * judge whether tabbing through a real page actually reaches every
     * interactive control in a sane order, or whether focus is visually
     * indicated at all (a `outline: none` with no replacement is invisible
     * to axe's static analysis — it only exists as a rendered, computed
     * style at a given focus state).
     */
    test.beforeAll(() => {
        artisan('oeparts:e2e:seed-storefront-fixture');
    });

    test.afterAll(() => {
        artisan('oeparts:e2e:seed-storefront-fixture --cleanup');
    });

    test('homepage: Tab reaches primary nav links and each stop has a visible focus indicator', async ({ page }) => {
        await page.goto('/en/', { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(400);
        await dismissCookieBanner(page);

        const seenVisibleFocus = [];
        for (let i = 0; i < 15; i++) {
            await page.keyboard.press('Tab');
            const info = await page.evaluate(() => {
                const el = document.activeElement;
                if (!el || el === document.body) return null;

                // outline/box-shadow alone is too narrow a check — this
                // navbar (see focus-visible:bg-ink/5 etc.) indicates focus
                // via a background-color (or border-color) change instead,
                // which a bare outline/box-shadow check would wrongly flag
                // as "no indicator at all". Diff the element's own computed
                // style focused vs. blurred instead: if ANYTHING visibly
                // relevant changes, focus is indicated somehow.
                const snapshot = (s) => ({
                    outline: `${s.outlineStyle} ${s.outlineWidth} ${s.outlineColor}`,
                    boxShadow: s.boxShadow,
                    backgroundColor: s.backgroundColor,
                    borderColor: s.borderColor,
                    color: s.color,
                    textDecorationLine: s.textDecorationLine,
                });
                const focused = snapshot(getComputedStyle(el));
                el.blur();
                const blurred = snapshot(getComputedStyle(el));
                el.focus();

                const visibleFocus = Object.keys(focused).some((k) => focused[k] !== blurred[k]);

                return {
                    tag: el.tagName,
                    text: (el.textContent || el.getAttribute('aria-label') || '').trim().slice(0, 40),
                    visibleFocus,
                };
            });
            if (info) seenVisibleFocus.push(info);
        }

        expect(seenVisibleFocus.length, 'Tab never moved focus onto any element').toBeGreaterThan(0);

        const withoutVisibleFocus = seenVisibleFocus.filter((s) => !s.visibleFocus);
        expect(
            withoutVisibleFocus,
            `focus stop(s) with no visible focus indicator (outline/box-shadow both 'none'): ${JSON.stringify(withoutVisibleFocus)}`,
        ).toEqual([]);
    });

    test('checkout step 1: contact form fields are reachable and submittable via keyboard alone', async ({ page }) => {
        await goToCheckoutStep1(page);
        await page.waitForTimeout(400);

        // Not input[type="email"].first() — the site-wide auth modal (hidden
        // off-canvas on every page, including checkout) has its own email
        // field earlier in the DOM; .focus() on a hidden element silently
        // no-ops, which previously made this test target the wrong input.
        const emailInput = page.locator('#checkout-email');
        await emailInput.focus();
        await expect(emailInput).toBeFocused();
        await page.keyboard.type('keyboard-user@example.com');

        // Tab forward until focus lands on the step's continue/submit control
        // (or we run out of reasonable attempts) — proves the form doesn't
        // trap focus or skip past its own submit button via a bad tabindex.
        let reachedSubmit = false;
        for (let i = 0; i < 10 && !reachedSubmit; i++) {
            await page.keyboard.press('Tab');
            reachedSubmit = await page.evaluate(() => {
                const el = document.activeElement;
                return !!el && el.tagName === 'BUTTON' && el.type === 'submit';
            });
        }
        expect(reachedSubmit, 'Tab never reached a submit button after filling the email field').toBe(true);
    });
});

test.describe('Text-zoom reflow (WCAG 1.4.4 / 1.4.10)', () => {
    /**
     * Simulates browser zoom by shrinking the viewport in proportion
     * (1280px design @ 200% zoom ≈ 640px of effective CSS layout room),
     * rather than the CSS `zoom` property: applying `zoom` to
     * `documentElement` and then measuring `documentElement.scrollWidth` /
     * `clientWidth` on that same (now-zoomed) element is a known-unreliable
     * self-measurement in Chromium — confirmed empirically here (every page
     * reported near-identical "overflow" at a given zoom level regardless
     * of actual content, the signature of a measurement artifact, not a
     * real per-page layout bug). A real low-vision user zooming via
     * Ctrl/Cmd+"+" keeps the same physical screen but has less effective
     * CSS-pixel width to lay content out in, which the viewport-shrink
     * technique models directly without the self-measurement issue.
     */
    const ZOOM_PAGES = [
        { label: 'homepage', path: '/en/' },
        { label: 'search-results', path: `/en/parts/${FIXTURE_QUERY_MULTI}` },
        { label: 'cart-empty', path: '/en/cart' },
    ];

    for (const zoom of [1.5, 2.0]) {
        for (const pg of ZOOM_PAGES) {
            test(`${pg.label} @ ${zoom * 100}% zoom: no horizontal overflow, primary nav still reachable`, async ({ page }) => {
                const width = Math.round(1280 / zoom);
                await page.setViewportSize({ width, height: 800 });
                await page.goto(pg.path, { waitUntil: 'domcontentloaded' });
                await page.waitForTimeout(400);
                await dismissCookieBanner(page);

                const overflow = await page.evaluate(
                    () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
                );
                expect(overflow, `${pg.label} @ ${zoom * 100}% zoom (viewport ${width}px) has horizontal overflow of ${overflow}px`).toBeLessThanOrEqual(1);

                await page.screenshot({ path: `test-results/accessibility-zoom/${pg.label}--${zoom * 100}pct.png`, fullPage: true });
            });
        }
    }
});
