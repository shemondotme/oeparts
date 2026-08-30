import { test, expect } from '@playwright/test';
import { artisan } from '../helpers.js';

/**
 * Sitewide responsive/visual-QA sweep — not a functional regression suite
 * (those already exist), this specifically hunts for the class of bugs a
 * fixed 1280x800 desktop viewport (this project's only configured guest
 * viewport, see playwright.config.js) can never catch: content bleeding
 * past its own container at phone widths, and overlay elements (the OEM
 * autocomplete dropdown, the mobile nav panel, the auth modal) getting
 * visually buried under later page content because of a CSS stacking
 * context they can't escape.
 *
 * Two kinds of checks:
 *   1. Automated — page-level horizontal overflow (scrollWidth vs
 *      clientWidth), and an `elementFromPoint` probe that proves an open
 *      overlay is really the topmost thing painted at its own coordinates
 *      (this is what would have caught the OEM-autocomplete-buried-under-
 *      the-next-section bug fixed earlier this session).
 *   2. Screenshot-only — full-page mobile screenshots saved for manual
 *      visual review (the "27 Countries" bleeding out of its grid cell and
 *      the corner-bracket/text collisions were only ever caught this way;
 *      no cheap DOM heuristic reliably flags "this text looks wrong" without
 *      a wall of false positives from intentionally-overflowing decorative
 *      elements).
 *
 * Fixtures: reuses the two existing deterministic e2e fixtures (guest-search.spec.js's
 * SeedE2eGuestFixture, catalog-browse.spec.js's SeedE2eStorefrontFixture) so
 * product-detail/manufacturer/car-model pages have a stable target instead of
 * depending on whatever the demo catalog happens to contain.
 */

const VIEWPORTS = [
    { name: 'mobile', width: 375, height: 812 },
    { name: 'mobile-lg', width: 428, height: 926 },
    { name: 'tablet', width: 768, height: 1024 },
    { name: 'desktop', width: 1280, height: 800 },
];

const FIXTURE_OEM = 'E2EGUEST001';
const FIXTURE_QUERY_MULTI = 'E2EGUEST'; // matches E2EGUEST001 + E2EGUESTX1 -> results listing, not a redirect
const FIXTURE_MANUFACTURER_SLUG = 'e2e-storefront-fixture';
const FIXTURE_CAR_MODEL_SLUG = 'e2e-storefront-fixture-model';
const CART_OEM_A = 'E2ESTOREA1';

const SCREENS_DIR = 'test-results/responsive-audit';

/**
 * The GDPR cookie-consent banner is deliberately the highest-stacked thing on
 * the page (as it should be — a compliance control must never be buried under
 * page content) and, until dismissed, covers a large share of the viewport,
 * especially at mobile widths. A real visitor deals with it before doing
 * anything else, so overlay checks below do too — otherwise every check past
 * the first page load would just be reporting "the consent banner is on top
 * of X", which is correct behavior, not a bug.
 */
async function dismissCookieBanner(page) {
    const acceptButton = page.getByRole('button', { name: /accept all cookies/i });
    try {
        await acceptButton.click({ timeout: 3000 });
    } catch {
        // Already dismissed (e.g. a prior test in the same context) or not present.
    }
}

const PAGES = [
    { label: 'homepage', path: '/en/' },
    { label: 'search-console', path: '/en/parts' },
    { label: 'search-results-multi', path: `/en/parts/${FIXTURE_QUERY_MULTI}` },
    { label: 'zero-results', path: '/en/parts/NOSUCHOEMXYZ999NOPE' },
    { label: 'brands-index', path: '/en/brands' },
    { label: 'manufacturer-show', path: `/en/brand/${FIXTURE_MANUFACTURER_SLUG}` },
    { label: 'car-model-show', path: `/en/brand/${FIXTURE_MANUFACTURER_SLUG}/${FIXTURE_CAR_MODEL_SLUG}` },
    { label: 'blog-index', path: '/en/blog' },
    { label: 'cart-empty', path: '/en/cart' },
    { label: 'contact', path: '/en/contact' },
    { label: 'error-404', path: '/en/this-page-does-not-exist-xyz-audit' },
];

test.describe('Responsive layout audit', () => {
    test.beforeAll(() => {
        artisan('oeparts:e2e:seed-guest-fixture');
        artisan('oeparts:e2e:seed-storefront-fixture');
    });

    test.afterAll(() => {
        artisan('oeparts:e2e:seed-guest-fixture --cleanup');
        artisan('oeparts:e2e:seed-storefront-fixture --cleanup');
    });

    for (const vp of VIEWPORTS) {
        test.describe(`@ ${vp.name} (${vp.width}px)`, () => {
            for (const pg of PAGES) {
                test(`${pg.label}: no horizontal overflow`, async ({ page }) => {
                    await page.setViewportSize({ width: vp.width, height: vp.height });
                    await page.goto(pg.path, { waitUntil: 'domcontentloaded' });
                    // Let entrance animations (bp-rise) and any layout-affecting
                    // web-font swap settle before measuring.
                    await page.waitForTimeout(400);

                    const { scrollWidth, clientWidth } = await page.evaluate(() => ({
                        scrollWidth: document.documentElement.scrollWidth,
                        clientWidth: document.documentElement.clientWidth,
                    }));
                    expect(scrollWidth, `${pg.label} @ ${vp.width}px: scrollWidth ${scrollWidth} vs viewport ${clientWidth}`)
                        .toBeLessThanOrEqual(clientWidth + 1);

                    await page.screenshot({ path: `${SCREENS_DIR}/${pg.label}--${vp.name}.png`, fullPage: true });
                });
            }
        });
    }
});

test.describe('Overlay stacking-context checks', () => {
    test.beforeAll(() => {
        artisan('oeparts:e2e:seed-guest-fixture');
    });

    test.afterAll(() => {
        artisan('oeparts:e2e:seed-guest-fixture --cleanup');
    });

    for (const vp of [VIEWPORTS[0], VIEWPORTS[3]]) { // mobile + desktop only
        test(`OEM autocomplete dropdown is not buried under later content @ ${vp.name}`, async ({ page }) => {
            await page.setViewportSize({ width: vp.width, height: vp.height });
            await page.goto('/en/parts', { waitUntil: 'domcontentloaded' });
            await page.waitForTimeout(400);
            await dismissCookieBanner(page);

            const input = page.locator('#oem-autocomplete-listbox').locator('..').locator('input[type="text"]:not([name="website"])');
            await input.fill(FIXTURE_QUERY_MULTI);

            const listbox = page.locator('#oem-autocomplete-listbox');
            // This dev environment's PHP built-in server + uncached bootstrap runs
            // a couple seconds slower than production would (confirmed via direct
            // timing during this audit) — generous but not unbounded.
            await expect(listbox).toBeVisible({ timeout: 12000 });
            await expect(listbox.locator('[role="option"]').first()).toBeVisible();

            await page.screenshot({ path: `${SCREENS_DIR}/search-dropdown-open--${vp.name}.png`, fullPage: false });

            // Probe several points across the dropdown's own box (not just its
            // top-left corner) and assert the topmost painted element at each
            // point is the dropdown itself or one of its descendants — proving
            // nothing later in the DOM is painted over any part of it.
            const probeResults = await page.evaluate(() => {
                const box = document.querySelector('#oem-autocomplete-listbox');
                const rect = box.getBoundingClientRect();
                const points = [
                    [rect.left + 10, rect.top + 5],
                    [rect.left + rect.width / 2, rect.top + rect.height / 2],
                    [rect.left + 10, rect.bottom - 5],
                    [rect.right - 10, rect.bottom - 5],
                ];
                // elementFromPoint legitimately returns null past the viewport edge —
                // a long suggestion list can run off the bottom of a short mobile
                // viewport with nothing overlapping it at all, which isn't the bug
                // this test hunts for. Only probe points actually on screen.
                return points
                    .filter(([x, y]) => x >= 0 && x < window.innerWidth && y >= 0 && y < window.innerHeight)
                    .map(([x, y]) => {
                        const el = document.elementFromPoint(x, y);
                        return { x, y, isInside: !!(el && box.contains(el)) };
                    });
            });

            for (const p of probeResults) {
                expect(p.isInside, `point (${p.x}, ${p.y}) inside the dropdown was painted over by another element`).toBe(true);
            }
        });
    }

    test('mobile nav panel is not buried under page content @ mobile', async ({ page }) => {
        await page.setViewportSize({ width: 375, height: 812 });
        await page.goto('/en/', { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(400);
        await dismissCookieBanner(page);

        await page.locator('button[aria-label="Toggle navigation menu"]').click();
        const panel = page.locator('#mobile-menu');
        await expect(panel).toBeVisible();

        await page.screenshot({ path: `${SCREENS_DIR}/mobile-menu-open--mobile.png`, fullPage: false });

        const covered = await page.evaluate(() => {
            const box = document.querySelector('#mobile-menu');
            const rect = box.getBoundingClientRect();
            const cx = rect.left + rect.width / 2;
            const cy = rect.top + Math.min(40, rect.height / 2);
            const el = document.elementFromPoint(cx, cy);
            return !!(el && box.contains(el));
        });
        expect(covered, 'mobile nav panel center is painted over by another element').toBe(true);
    });

    test('auth modal is not buried under page content @ mobile', async ({ page }) => {
        await page.setViewportSize({ width: 375, height: 812 });
        await page.goto('/en/', { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(400);
        await dismissCookieBanner(page);

        // Below the `sm` breakpoint the topbar's own Sign In button is
        // `hidden sm:flex` (resources/views/components/navbar.blade.php) — the
        // real entry point at this width is inside the mobile menu drawer.
        await page.locator('button[aria-label="Toggle navigation menu"]').click();
        await page.locator('#mobile-menu').getByRole('button', { name: /sign/i }).click();
        const emailInput = page.locator('#login-email');
        await expect(emailInput).toBeVisible();
        // x-show's toBeVisible() resolves as soon as `display` flips, well
        // before the modal's own 300ms opacity fade (auth-modal.blade.php)
        // finishes — wait it out so the screenshot captures the settled
        // state, not a half-transparent mid-fade frame.
        await page.waitForTimeout(350);

        await page.screenshot({ path: `${SCREENS_DIR}/auth-modal-open--mobile.png`, fullPage: false });

        const covered = await page.evaluate(() => {
            const el = document.getElementById('login-email');
            const rect = el.getBoundingClientRect();
            const top = document.elementFromPoint(rect.left + rect.width / 2, rect.top + rect.height / 2);
            return !!(top && (top === el || el.contains(top) || top.contains(el)));
        });
        expect(covered, 'auth modal email field is painted over by another element').toBe(true);
    });
});

test.describe('Cart and checkout at mobile width', () => {
    test.beforeAll(() => {
        artisan('oeparts:e2e:seed-storefront-fixture');
    });

    test.afterAll(() => {
        artisan('oeparts:e2e:seed-storefront-fixture --cleanup');
    });

    test('cart with an item and the checkout page render cleanly at mobile', async ({ page }) => {
        await page.setViewportSize({ width: 375, height: 812 });

        await page.goto(`/en/parts/${CART_OEM_A}`, { waitUntil: 'domcontentloaded' });
        await page.waitForURL(new RegExp(`/en/parts/${CART_OEM_A}/\\d+-`), { waitUntil: 'domcontentloaded' });
        await dismissCookieBanner(page);
        await page.getByRole('button', { name: /add to cart/i }).first().click();
        await page.waitForTimeout(800);

        await page.goto('/en/cart', { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(400);
        // Known race, documented in cart.spec.js: navigating straight from a PDP's
        // "Add to Cart" to /cart can intermittently render the empty-cart state
        // even though the item was really added server-side (the add AJAX call
        // hasn't settled before this very next navigation). Same one-reload
        // workaround as addToCartByOem there.
        if (await page.getByText(/cart is empty/i).count() > 0) {
            await page.reload({ waitUntil: 'domcontentloaded' });
            await page.waitForTimeout(400);
        }
        const cartOverflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(cartOverflow, 'cart page has horizontal overflow at mobile width').toBeLessThanOrEqual(1);
        await page.screenshot({ path: `${SCREENS_DIR}/cart-with-item--mobile.png`, fullPage: true });

        const checkoutLink = page.locator('main a[href$="/checkout"]').first();
        if (await checkoutLink.count() > 0) {
            await checkoutLink.click();
            await page.waitForURL(/\/en\/checkout$/, { waitUntil: 'domcontentloaded' });
            await page.waitForTimeout(400);
            const checkoutOverflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
            expect(checkoutOverflow, 'checkout page has horizontal overflow at mobile width').toBeLessThanOrEqual(1);
            await page.screenshot({ path: `${SCREENS_DIR}/checkout--mobile.png`, fullPage: true });
        }
    });
});
