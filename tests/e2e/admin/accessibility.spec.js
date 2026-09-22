import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

/**
 * Phase 6 (Accessibility, WCAG 2.1 AA) — admin (Filament) panel sweep.
 * Same page selection as responsive-audit.spec.js (representative UI
 * shapes: dashboard widgets, data table, multi-section form, settings page)
 * — Filament resources share the same underlying table/form/page
 * components, so a violation in one of those shows up across all of them,
 * not just the page tested. See guest/accessibility.spec.js for why
 * axe-core's WCAG-tagged ruleset is the right tool for the automated half
 * of this phase, and why keyboard-nav needs a hand-written check alongside
 * it.
 *
 * No explicit login: the `chromium` project (playwright.config.js) already
 * carries an authenticated storageState via the shared `setup` project.
 */

const WCAG_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'];

function formatViolations(results) {
    return results.violations
        .map((v) => `[${v.impact}] ${v.id}: ${v.help} (${v.nodes.length} node(s)) — ${v.nodes.slice(0, 3).map((n) => n.target.join(' ')).join(' | ')}`)
        .join('\n');
}

const PAGES = [
    { label: 'dashboard', path: '/admin' },
    { label: 'products-list', path: '/admin/products' },
    { label: 'product-create', path: '/admin/products/create' },
    { label: 'orders-list', path: '/admin/orders' },
    { label: 'manufacturers-list', path: '/admin/manufacturers' },
    { label: 'site-copy-library', path: '/admin/settings/site-copy-library' },
    { label: 'permission-matrix', path: '/admin/system/permission-matrix' },
];

test.describe('Admin panel accessibility (WCAG 2.1 A/AA) — light theme', () => {
    for (const pg of PAGES) {
        test(`${pg.label}: no WCAG 2.1 A/AA violations`, async ({ page }) => {
            await page.goto(pg.path, { waitUntil: 'domcontentloaded' });
            await page.waitForSelector('nav.fi-topbar', { timeout: 30000 });
            await page.waitForTimeout(500);

            const results = await new AxeBuilder({ page }).withTags(WCAG_TAGS).analyze();
            expect(results.violations, formatViolations(results)).toEqual([]);
        });
    }
});

test.describe('Admin panel accessibility (WCAG 2.1 A/AA) — dark theme', () => {
    /**
     * Filament reads localStorage['theme'] on initial paint (see
     * vendor/filament/filament/resources/js/dark-mode.js and
     * .../components/layout/base.blade.php) — setting it before navigation
     * via addInitScript is the same mechanism the real theme-switcher
     * button uses, just without needing to click through the UI menu first
     * on every test. Dark theme gets its own scan rather than trusting the
     * light-theme pass: color-contrast is exactly the kind of check a CSS
     * custom-property override (this panel's "Industrial Blueprint" theme,
     * see responsive-audit.spec.js) can silently break in one mode but not
     * the other.
     */
    test.beforeEach(async ({ page }) => {
        await page.addInitScript(() => localStorage.setItem('theme', 'dark'));
    });

    const DARK_PAGES = PAGES.filter((pg) => ['dashboard', 'products-list', 'product-create'].includes(pg.label));

    for (const pg of DARK_PAGES) {
        test(`${pg.label} (dark): no WCAG 2.1 A/AA violations`, async ({ page }) => {
            await page.goto(pg.path, { waitUntil: 'domcontentloaded' });
            await page.waitForSelector('nav.fi-topbar', { timeout: 30000 });
            await page.waitForTimeout(500);

            const isDark = await page.evaluate(() => document.documentElement.classList.contains('dark'));
            expect(isDark, 'dark theme did not actually apply — html.dark class missing').toBe(true);

            const results = await new AxeBuilder({ page }).withTags(WCAG_TAGS).analyze();
            expect(results.violations, formatViolations(results)).toEqual([]);
        });
    }
});

test.describe('Admin keyboard-only navigation', () => {
    test('dashboard: Tab reaches sidebar links and each stop has a visible focus indicator', async ({ page }) => {
        await page.goto('/admin', { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('nav.fi-topbar', { timeout: 30000 });
        await page.waitForTimeout(500);

        const seenVisibleFocus = [];
        for (let i = 0; i < 15; i++) {
            await page.keyboard.press('Tab');
            const info = await page.evaluate(() => {
                const el = document.activeElement;
                if (!el || el === document.body) return null;

                // See guest/accessibility.spec.js's homepage focus test for
                // why this diffs focused-vs-blurred computed style instead
                // of only checking outline/box-shadow — a focus indicator
                // via background-color or border-color alone would
                // otherwise be wrongly flagged as "no indicator".
                const snapshot = (s) => ({
                    outline: `${s.outlineStyle} ${s.outlineWidth} ${s.outlineColor}`,
                    boxShadow: s.boxShadow,
                    backgroundColor: s.backgroundColor,
                    borderColor: s.borderColor,
                    color: s.color,
                });
                const focused = snapshot(getComputedStyle(el));
                el.blur();
                const blurred = snapshot(getComputedStyle(el));
                el.focus();

                return {
                    tag: el.tagName,
                    text: (el.textContent || el.getAttribute('aria-label') || '').trim().slice(0, 40),
                    visibleFocus: Object.keys(focused).some((k) => focused[k] !== blurred[k]),
                };
            });
            if (info) seenVisibleFocus.push(info);
        }

        expect(seenVisibleFocus.length, 'Tab never moved focus onto any element').toBeGreaterThan(0);

        const withoutVisibleFocus = seenVisibleFocus.filter((s) => !s.visibleFocus);
        expect(
            withoutVisibleFocus,
            `focus stop(s) with no visible focus indicator: ${JSON.stringify(withoutVisibleFocus)}`,
        ).toEqual([]);
    });

    test('a "skip to content" link is the first Tab stop and jumps past the sidebar', async ({ page }) => {
        // WCAG 2.4.1 (Bypass Blocks) regression guard. This is Filament core's
        // own built-in skip link (fi-skip-link, index.blade.php) — not
        // something this app added. The very first version of this suite's
        // "products list" test below blindly tabbed 40 times without ever
        // pressing Enter on it, so it never got exercised and the table
        // looked unreachable; that was a test gap, not a missing feature.
        // This test pins the mechanism directly so a future regression
        // (e.g. a panel customization that hides or breaks it) gets caught.
        await page.goto('/admin/products', { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('nav.fi-topbar', { timeout: 30000 });

        await page.keyboard.press('Tab');
        const skipLink = page.locator('a[href="#fi-main-content"]');
        await expect(skipLink).toBeFocused();

        await page.keyboard.press('Enter');
        await expect(page.locator('#fi-main-content')).toBeFocused();
    });

    test('products list: a table row action is reachable via keyboard alone', async ({ page }) => {
        await page.goto('/admin/products', { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('nav.fi-topbar', { timeout: 30000 });
        // This dev DB is seeded to 100k+ products (Phase 4) — the table body
        // loads asynchronously via Livewire and can still be a spinner well
        // past topbar-ready; wait for an actual row, not a fixed delay.
        await page.waitForSelector('table tbody tr', { timeout: 30000 });
        await page.waitForTimeout(500);

        // A real keyboard user uses the skip link, not 40 blind Tab presses.
        await page.keyboard.press('Tab');
        await page.keyboard.press('Enter');

        let reachedRowLink = false;
        for (let i = 0; i < 15 && !reachedRowLink; i++) {
            await page.keyboard.press('Tab');
            reachedRowLink = await page.evaluate(() => {
                const el = document.activeElement;
                return !!el && !!el.closest('table');
            });
        }
        expect(reachedRowLink, 'Tab never reached an element inside the products table within 15 stops after the skip link').toBe(true);
    });
});
