import { test, expect } from '@playwright/test';
import { artisan } from '../helpers.js';

/**
 * Anonymous-visitor storefront flows: hub search -> single-match
 * auto-redirect to the product detail page -> gallery thumbnail swap ->
 * cross-reference navigation. Runs in the `guest` Playwright project
 * (playwright.config.js) — no `setup` dependency, no storageState, a real
 * anonymous session throughout.
 *
 * The demo catalog seeder (DemoManufacturersAndPartsSeeder, run by default
 * via DatabaseSeeder) creates products but no images or cross-references,
 * so this suite seeds its own deterministic fixture via a dedicated
 * artisan command rather than assuming those happen to already exist in
 * whatever dev DB this runs against — see
 * app/Console/Commands/SeedE2eGuestFixture.php for exactly what it
 * creates/removes (a uniquely-named manufacturer/product/images/
 * cross-references, idempotent, with its own PHPUnit coverage).
 *
 * Fixture is seeded via the `artisan()` helper (helpers.js), which runs
 * through `docker compose exec laravel.test` — this dev environment has
 * no host PHP on PATH, only the Docker Sail containers.
 */

const FIXTURE_OEM = 'E2EGUEST001';
const CROSS_OEM = 'E2EGUESTX1';

test.describe('Guest storefront: search -> detail -> gallery -> cross-reference', () => {
    test.beforeAll(() => {
        artisan('oeparts:e2e:seed-guest-fixture');
    });

    test.afterAll(() => {
        artisan('oeparts:e2e:seed-guest-fixture --cleanup');
    });

    test('searching the homepage hero for the fixture OEM auto-redirects to its detail page', async ({ page }) => {
        await page.goto('/en/', { waitUntil: 'domcontentloaded' });

        const searchInput = page.locator('#hero-oem-search');
        await expect(searchInput).toBeVisible();

        await searchInput.fill(FIXTURE_OEM);
        await searchInput.press('Enter');

        // A single exact match with detail pages enabled 301s straight to
        // the detail URL (/en/parts/{oem}/{id}-{slug}) — the browser
        // follows that server-side redirect transparently.
        await page.waitForURL(new RegExp(`/en/parts/${FIXTURE_OEM}/\\d+-`), { waitUntil: 'domcontentloaded' });

        await expect(page.locator('h1')).toContainText('E2E Guest Fixture Brake Pad');
        await expect(page.locator('.oem-number')).toContainText(FIXTURE_OEM);
    });

    test('clicking a gallery thumbnail swaps the main product image', async ({ page }) => {
        await page.goto(`/en/parts/${FIXTURE_OEM}`, { waitUntil: 'domcontentloaded' });
        await page.waitForURL(new RegExp(`/en/parts/${FIXTURE_OEM}/\\d+-`), { waitUntil: 'domcontentloaded' });

        const mainImage = page.locator('[data-testid="product-main-image"]');
        const thumbnails = page.locator('[data-testid="product-thumbnail"]');

        await expect(mainImage).toBeVisible();
        await expect(thumbnails).toHaveCount(2);

        const initialSrc = await mainImage.getAttribute('src');

        // The fixture's second image (sort_order 1, not featured) always
        // has a different underlying path than the featured image the
        // main slot starts with — clicking it is guaranteed to be a real
        // change, not a no-op click on the already-shown image.
        await thumbnails.last().click();

        await expect(mainImage).not.toHaveAttribute('src', initialSrc);
    });

    test('clicking a cross-reference number navigates to that number\'s own hub page', async ({ page }) => {
        await page.goto(`/en/parts/${FIXTURE_OEM}`, { waitUntil: 'domcontentloaded' });
        await page.waitForURL(new RegExp(`/en/parts/${FIXTURE_OEM}/\\d+-`), { waitUntil: 'domcontentloaded' });

        const crossRefLink = page.locator('[data-testid="product-cross-ref-link"]', { hasText: CROSS_OEM });
        await expect(crossRefLink).toBeVisible();

        await crossRefLink.click();

        // Only the fixture product carries this cross-reference, so this
        // is itself a single cross-reference match — it auto-redirects
        // straight to the product's detail page rather than stopping on
        // the hub. The redirect target uses the product's own PRIMARY OEM
        // (SearchController::results() builds it from $product->normalized_oem,
        // not the searched cross-ref segment), so the URL lands back on
        // FIXTURE_OEM, not CROSS_OEM.
        await page.waitForURL(new RegExp(`/en/parts/${FIXTURE_OEM}/\\d+-`), { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toContainText('E2E Guest Fixture Brake Pad');
    });
});
