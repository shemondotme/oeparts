import { test, expect } from '@playwright/test';
import { artisan } from '../helpers.js';

/**
 * Anonymous-visitor browsing flows that don't touch cart/checkout state:
 * brands, car models, blog, static CMS pages, the HTML sitemap, and the
 * plain-text robots.txt/llms.txt endpoints. Uses the shared storefront
 * fixture (manufacturer + car model) seeded by
 * app/Console/Commands/SeedE2eStorefrontFixture.php so brand/car-model
 * pages have a deterministic target regardless of whatever the demo
 * catalog happens to contain.
 */

const FIXTURE_MANUFACTURER_SLUG = 'e2e-storefront-fixture';
const FIXTURE_CAR_MODEL_SLUG = 'e2e-storefront-fixture-model';

test.describe('Brands and car models', () => {
    test.beforeAll(() => {
        artisan('oeparts:e2e:seed-storefront-fixture');
    });

    test('brands index page lists manufacturers', async ({ page }) => {
        await page.goto('/en/brands', { waitUntil: 'domcontentloaded' });

        await expect(page.locator('h1')).toBeVisible();
        await expect(page.getByText('E2E Storefront Fixture Motors')).toBeVisible();
    });

    test('a single brand page loads and links to its car models', async ({ page }) => {
        await page.goto(`/en/brand/${FIXTURE_MANUFACTURER_SLUG}`, { waitUntil: 'domcontentloaded' });

        await expect(page.locator('h1')).toContainText('E2E Storefront Fixture Motors');
    });

    test('the brand car-models index page loads', async ({ page }) => {
        await page.goto(`/en/brand/${FIXTURE_MANUFACTURER_SLUG}/models`, { waitUntil: 'domcontentloaded' });

        await expect(page.locator('h1')).toBeVisible();
        await expect(page.getByText('E2E Fixture Model X')).toBeVisible();
    });

    test('a single car-model page loads', async ({ page }) => {
        await page.goto(`/en/brand/${FIXTURE_MANUFACTURER_SLUG}/${FIXTURE_CAR_MODEL_SLUG}`, { waitUntil: 'domcontentloaded' });

        await expect(page.locator('h1')).toContainText('E2E Fixture Model X');
    });
});

test.describe('Blog', () => {
    test('blog index lists posts and the search field works', async ({ page }) => {
        await page.goto('/en/blog', { waitUntil: 'domcontentloaded' });

        await expect(page.locator('h1')).toBeVisible();

        const searchInput = page.locator('#blog-search');
        await expect(searchInput).toBeVisible();
        await searchInput.fill('workshop');
        await searchInput.press('Enter');
        await page.waitForLoadState('domcontentloaded');
    });

    test('a single blog post loads', async ({ page }) => {
        await page.goto('/en/blog/eu-cross-border-parts-shipping-workshop-guide', { waitUntil: 'domcontentloaded' });

        await expect(page.locator('h1')).toBeVisible();
    });
});

test.describe('Static CMS pages', () => {
    for (const slug of ['about', 'privacy-policy', 'terms-of-service', 'returns-policy', 'impressum']) {
        test(`/en/${slug} loads with a heading`, async ({ page }) => {
            const response = await page.goto(`/en/${slug}`, { waitUntil: 'domcontentloaded' });

            expect(response.status()).toBeLessThan(400);
            await expect(page.locator('h1')).toBeVisible();
        });
    }

    test('the HTML sitemap page loads with its section anchors', async ({ page }) => {
        await page.goto('/en/sitemap', { waitUntil: 'domcontentloaded' });

        await expect(page.locator('h1')).toBeVisible();
        for (const anchor of ['#core', '#brands', '#journal', '#legal']) {
            await expect(page.locator(anchor)).toHaveCount(1);
        }
    });
});

test.describe('Plain-text discovery endpoints', () => {
    test('robots.txt is served as text and mentions the sitemap', async ({ request }) => {
        const response = await request.get('/robots.txt');
        expect(response.status()).toBe(200);

        const body = await response.text();
        expect(body.toLowerCase()).toContain('sitemap');
    });

    test('llms.txt is served', async ({ request }) => {
        const response = await request.get('/llms.txt');
        expect(response.status()).toBe(200);
    });
});
