import { test, expect } from '@playwright/test';
import { artisan } from '../helpers.js';

/**
 * Anonymous-visitor product detail page: specifications, add-to-cart,
 * guest review submission, and the "ask about fitment" inquiry modal.
 * Selectors taken directly from resources/views/frontend/search/detail.blade.php
 * and resources/views/components/modals/part-inquiry.blade.php.
 *
 * Buy Now is intentionally not covered here — pdp.buy_now_enabled is off
 * in this environment (confirmed via `settings('pdp.buy_now_enabled')`),
 * so there is nothing to click; re-enable and add coverage if the setting
 * is ever turned on for real.
 */

const FIXTURE_OEM = 'E2ESTOREA1';

test.describe('Product detail page', () => {
    test.beforeAll(() => {
        artisan('oeparts:e2e:seed-storefront-fixture');
    });

    test('loads via its OEM, showing the specifications section', async ({ page }) => {
        await page.goto(`/en/parts/${FIXTURE_OEM}`, { waitUntil: 'domcontentloaded' });
        await page.waitForURL(new RegExp(`/en/parts/${FIXTURE_OEM}/\\d+-`), { waitUntil: 'domcontentloaded' });

        await expect(page.locator('h1')).toContainText('E2E Storefront Fixture Filter');
        await expect(page.locator('.oem-number')).toContainText(FIXTURE_OEM);
    });

    test('clicking Add to Cart transitions the button to its added state', async ({ page }) => {
        await page.goto(`/en/parts/${FIXTURE_OEM}`, { waitUntil: 'domcontentloaded' });
        await page.waitForURL(new RegExp(`/en/parts/${FIXTURE_OEM}/\\d+-`), { waitUntil: 'domcontentloaded' });

        const addToCart = page.locator('[data-testid="product-add-to-cart"]');
        await addToCart.click();

        await expect(addToCart).toContainText(/added/i, { timeout: 10000 });
    });

    test('a guest can submit a product review without logging in', async ({ page }) => {
        await page.goto(`/en/parts/${FIXTURE_OEM}`, { waitUntil: 'domcontentloaded' });
        await page.waitForURL(new RegExp(`/en/parts/${FIXTURE_OEM}/\\d+-`), { waitUntil: 'domcontentloaded' });

        // The form lives inside a <details>/<summary> disclosure — open it first.
        await page.getByText(/write a review/i).click();

        await page.locator('#review_reviewer_name').fill('E2E Playwright Reviewer');
        await page.locator('#review_title').fill('Solid fit, arrived on time');
        await page.locator('#review_rating').selectOption('5');
        await page.locator('#review_comment').fill('Seeded by the Playwright e2e suite — safe to ignore/delete.');

        await page.getByRole('button', { name: /submit/i }).click();

        // Reviews are stored as 'status' => 'pending' (ProductReviewController)
        // and never show up in the public list immediately — the only
        // observable outcome from the visitor's side is the flash message
        // (search.review_submitted_pending: "...has been submitted and
        // will appear once approved.").
        await expect(page.getByText(/submitted.*approved/i).first()).toBeVisible({ timeout: 10000 });
    });

    test('the "ask about fitment" button opens the part-inquiry modal pre-filled with the OEM', async ({ page }) => {
        await page.goto(`/en/parts/${FIXTURE_OEM}`, { waitUntil: 'domcontentloaded' });
        await page.waitForURL(new RegExp(`/en/parts/${FIXTURE_OEM}/\\d+-`), { waitUntil: 'domcontentloaded' });

        await page.locator('[data-testid="product-ask-fitment"]').click();

        const modal = page.locator('[role="dialog"][aria-labelledby="part-inquiry-modal-title"]');
        await expect(modal).toBeVisible();
        await expect(page.locator('#inquiry-oem')).toHaveValue(FIXTURE_OEM);
    });

    test('submitting the part-inquiry form with contact details succeeds', async ({ page }) => {
        await page.goto(`/en/parts/${FIXTURE_OEM}`, { waitUntil: 'domcontentloaded' });
        await page.waitForURL(new RegExp(`/en/parts/${FIXTURE_OEM}/\\d+-`), { waitUntil: 'domcontentloaded' });

        await page.locator('[data-testid="product-ask-fitment"]').click();

        await page.locator('#inquiry-email').fill('e2e-inquiry@example.com');
        await page.locator('#inquiry-phone').fill('+491234567890');

        await page.getByRole('button', { name: /submit/i }).click();

        // The modal stays open but swaps to its inline success state
        // (x-show="state === 'success'") once the fetch() POST to
        // frontend.inquiry.store resolves — it does not close itself.
        await expect(page.getByText('STATUS · TRANSMITTED')).toBeVisible({ timeout: 10000 });
    });
});
