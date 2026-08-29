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

async function addToCartAndEnterCheckout(page) {
    await page.goto(`/en/parts/${OEM_A}`, { waitUntil: 'domcontentloaded' });
    await page.waitForURL(new RegExp(`/en/parts/${OEM_A}/\\d+-`), { waitUntil: 'domcontentloaded' });
    const addToCart = page.locator('[data-testid="product-add-to-cart"]');
    await addToCart.click();
    await expect(addToCart).toContainText(/added/i, { timeout: 10000 });

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
        // give this specific, longest test in the suite more headroom.
        test.setTimeout(90000);

        await addToCartAndEnterCheckout(page);

        // Step 1: contact details.
        await expect(page.locator('#checkout-email')).toBeVisible({ timeout: 15000 });
        await page.locator('#checkout-email').fill('e2e-checkout@example.com');
        await page.locator('#checkout-phone').fill('+491234567890');
        await page.locator('button[type="submit"][form="checkout-form"]').click();

        // Step 2: shipping address.
        await expect(page.locator('#checkout_first_name')).toBeVisible({ timeout: 15000 });
        await page.locator('#checkout_first_name').fill('Playwright');
        await page.locator('#checkout_last_name').fill('Visitor');
        await page.locator('#checkout_street').fill('Teststrasse 1');
        await page.locator('#checkout_city').fill('Berlin');
        await page.locator('#checkout_postal_code').fill('10115');
        await page.locator('#checkout_country_code').selectOption('DE');
        await page.locator('button[type="submit"][form="checkout-form"]').click();

        // Step 3: shipping method.
        const shippingRadios = page.locator('input[name="shipping_method_id"]');
        await expect(shippingRadios.first()).toBeVisible({ timeout: 15000 });
        await shippingRadios.first().check();
        await page.locator('button[type="submit"][form="checkout-form"]').click();

        // Step 4: review + terms. Scoped to #checkout-form — the sitewide
        // (normally hidden) auth modal also has an `agree_terms` checkbox
        // on its Register tab, always present in the DOM on every page.
        const agreeTerms = page.locator('#checkout-form input[name="agree_terms"]');
        await expect(agreeTerms).toBeVisible({ timeout: 15000 });
        await agreeTerms.check();
        await page.locator('button[type="submit"][form="checkout-form"]').click();

        // Step 5: payment method -> creates the order. This step does real
        // work (stock check, order + order-item writes) before its
        // redirect, so wait for the resulting navigation alongside the
        // click rather than just the click's own (fast) actionability.
        const bankRadio = page.locator('input[name="payment_method"][value="bank_transfer"]');
        await expect(bankRadio).toBeVisible({ timeout: 15000 });
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
        await page.locator('button[type="submit"][form="checkout-form"]').click();

        await expect(page.locator('#checkout_first_name')).toBeVisible({ timeout: 15000 });
        // Leave every field blank and submit — server-side validation must
        // reject this and keep the visitor on step 2, not silently advance.
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
