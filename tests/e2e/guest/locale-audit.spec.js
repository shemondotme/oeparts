import { test, expect } from '@playwright/test';
import { artisan } from '../helpers.js';

/**
 * Multi-locale responsive sweep — responsive-audit.spec.js only ever ran
 * in English. German in particular is a real overflow risk this site
 * hasn't been checked against: compound nouns routinely run 2-3x longer
 * than their English equivalent with no natural break point (e.g. a
 * short English label can become one 25+ character German word), which
 * is exactly the shape of content that broke the "27 Countries" grid
 * cell and the CMS section headlines earlier in this project's history —
 * both fixed by adding min-w-0/break-words, but never re-checked against
 * genuinely long real-world text in another language.
 *
 * Same two-pronged approach as the English audit: automated horizontal-
 * overflow assertions across locales x viewports, plus mobile screenshots
 * for manual visual review.
 */

const LOCALES = ['de', 'lt', 'fr', 'es'];
const VIEWPORTS = [
    { name: 'mobile', width: 375, height: 812 },
    { name: 'tablet', width: 768, height: 1024 },
];

const FIXTURE_QUERY_MULTI = 'E2EGUEST';
const FIXTURE_MANUFACTURER_SLUG = 'e2e-storefront-fixture';

const SCREENS_DIR = 'test-results/locale-audit';

const PAGES = [
    { label: 'homepage', path: '/' },
    { label: 'search-console', path: '/parts' },
    { label: 'search-results-multi', path: `/parts/${FIXTURE_QUERY_MULTI}` },
    { label: 'brands-index', path: '/brands' },
    { label: 'manufacturer-show', path: `/brand/${FIXTURE_MANUFACTURER_SLUG}` },
    { label: 'blog-index', path: '/blog' },
    { label: 'cart-empty', path: '/cart' },
    { label: 'contact', path: '/contact' },
];

test.describe('Multi-locale responsive audit', () => {
    test.beforeAll(() => {
        artisan('oeparts:e2e:seed-guest-fixture');
        artisan('oeparts:e2e:seed-storefront-fixture');
    });

    test.afterAll(() => {
        artisan('oeparts:e2e:seed-guest-fixture --cleanup');
        artisan('oeparts:e2e:seed-storefront-fixture --cleanup');
    });

    for (const locale of LOCALES) {
        for (const vp of VIEWPORTS) {
            test.describe(`${locale} @ ${vp.name} (${vp.width}px)`, () => {
                for (const pg of PAGES) {
                    test(`${pg.label}: no horizontal overflow`, async ({ page }) => {
                        await page.setViewportSize({ width: vp.width, height: vp.height });
                        await page.goto(`/${locale}${pg.path}`, { waitUntil: 'domcontentloaded' });
                        await page.waitForTimeout(400);

                        const { scrollWidth, clientWidth } = await page.evaluate(() => ({
                            scrollWidth: document.documentElement.scrollWidth,
                            clientWidth: document.documentElement.clientWidth,
                        }));

                        await page.screenshot({ path: `${SCREENS_DIR}/${locale}--${pg.label}--${vp.name}.png`, fullPage: true });

                        expect(scrollWidth, `${locale}/${pg.label} @ ${vp.width}px: scrollWidth ${scrollWidth} vs viewport ${clientWidth}`)
                            .toBeLessThanOrEqual(clientWidth + 1);
                    });
                }
            });
        }
    }
});
