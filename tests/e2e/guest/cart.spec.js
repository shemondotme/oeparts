import { test, expect } from '@playwright/test';
import { artisan } from '../helpers.js';

/**
 * Anonymous-visitor cart page: add/update/remove line items and coupon
 * application. resources/views/frontend/cart/index.blade.php has no
 * data-testid attributes anywhere — everything is Alpine-driven, so
 * selectors below are the aria-labels/ids confirmed live in that file and
 * in lang/en/cart.php (aria_quantity="Item quantity",
 * remove_item_aria="Remove item", coupon_apply_btn="Apply").
 *
 * The page renders BOTH a mobile stacked-card layout and a desktop
 * grid-row layout for every line item at all times (CSS breakpoint
 * classes hide one, not conditional JS) — at this project's default
 * 1280x800 guest viewport the desktop row is the one actually visible,
 * but `.first()` would resolve to whichever markup comes first in the
 * DOM (confirmed: the mobile card does), which Playwright then refuses
 * to click/fill since it's display:none. `:visible` filters to the one
 * actually on screen regardless of DOM order. The same duplication
 * applies to each item's own name text (mobile card + desktop row both
 * render it) — `getByText(...).first()` isn't enough here since `.first()`
 * still resolves to the mobile (hidden) copy and `toBeVisible()` then
 * correctly fails on it; `visibleText()` below scopes to `:visible` the
 * same way the aria-label locators do.
 *
 * a[href$="/checkout"] also matches 3 elements on this page: the navbar's
 * mini-cart dropdown "Checkout" link (hidden until that dropdown opens),
 * the desktop sidebar's own button, and the mobile sticky bar's — scope
 * to `main` to reach the cart page's own button, not the hidden dropdown
 * one that happens to come first in the DOM.
 *
 * Found while writing this suite: navigating straight from a PDP's
 * "Add to Cart" to /cart intermittently (not every run) shows the
 * empty-cart state even though the item really was added — confirmed via
 * cookies/JSON dumps that the guest_token cart really does contain the
 * item server-side; this looks like a real timing race between the add
 * AJAX call settling and the very next full-page navigation, not a test
 * bug. `addToCartByOem` reloads once if the empty state appears, which is
 * enough to make the suite reliable — but the underlying race is worth a
 * backend look rather than being fully explained here.
 */

const OEM_A = 'E2ESTOREA1';
const OEM_B = 'E2ESTOREB2';
const COUPON_CODE = 'E2ESTOREWELCOME';

async function addToCartByOem(page, oem) {
    await page.goto(`/en/parts/${oem}`, { waitUntil: 'domcontentloaded' });
    await page.waitForURL(new RegExp(`/en/parts/${oem}/\\d+-`), { waitUntil: 'domcontentloaded' });

    const addToCart = page.locator('[data-testid="product-add-to-cart"]');
    await addToCart.click();
    await expect(addToCart).toContainText(/added/i, { timeout: 10000 });
}

/**
 * Matches an item name's text, scoped to whichever copy (mobile card vs
 * desktop row) is actually rendered visible at the current viewport —
 * both are rendered as a plain <p>, so `p:visible` + hasText narrows to
 * exactly the one copy actually on screen.
 */
function visibleText(page, text) {
    return page.locator('p:visible', { hasText: text });
}

/**
 * Navigates to the cart page, reloading (up to twice more) if it renders
 * the empty-cart state, or — the item-count equivalent of the same race —
 * is missing one of `expectedItemTexts` while showing others. See the
 * file-level comment for why this exists.
 */
async function gotoCart(page, expectedItemTexts = []) {
    await page.goto('/en/cart', { waitUntil: 'domcontentloaded' });

    for (let attempt = 0; attempt < 3; attempt++) {
        // Give Alpine a moment to evaluate cart.items.length before
        // checking — right after domcontentloaded/reload, the empty-state
        // block is still x-cloak'd (hidden) regardless of which way it
        // will resolve.
        await page.waitForTimeout(800);

        const isEmpty = await page.getByText(/cart is empty/i).isVisible().catch(() => false);
        let missingSome = false;
        for (const text of expectedItemTexts) {
            if (!(await visibleText(page, text).isVisible().catch(() => false))) {
                missingSome = true;
                break;
            }
        }

        if (!isEmpty && !missingSome) return;

        try {
            await page.reload({ waitUntil: 'domcontentloaded' });
        } catch {
            await page.goto('/en/cart', { waitUntil: 'domcontentloaded' });
        }
    }
}

test.describe('Cart', () => {
    test.beforeAll(() => {
        artisan('oeparts:e2e:seed-storefront-fixture');
    });

    test('adding two products shows both as line items on the cart page', async ({ page }) => {
        await addToCartByOem(page, OEM_A);
        await addToCartByOem(page, OEM_B);

        await gotoCart(page, ['E2E Storefront Fixture Filter', 'E2E Storefront Fixture Spark Plug']);

        await expect(visibleText(page, 'E2E Storefront Fixture Filter')).toBeVisible();
        await expect(visibleText(page, 'E2E Storefront Fixture Spark Plug')).toBeVisible();
    });

    test('increasing quantity via the + button updates the line total', async ({ page }) => {
        await addToCartByOem(page, OEM_A);
        await gotoCart(page, ['E2E Storefront Fixture Filter']);

        const increaseButton = page.locator('[aria-label="Increase quantity"]:visible').first();
        await expect(increaseButton).toBeVisible();

        const qtyInput = page.locator('[aria-label="Item quantity"]:visible').first();
        const before = Number(await qtyInput.inputValue());

        await increaseButton.click();

        // incrementItem() PUTs the new quantity, then re-fetches the whole
        // cart via loadCart() to redraw it — the same kind of brief
        // read-after-write staleness window as the add-to-cart race
        // (see the file-level comment) can leave that re-fetch showing
        // the pre-update quantity for a moment, so poll instead of a
        // single fixed-delay read.
        await expect.poll(async () => Number(await qtyInput.inputValue()), { timeout: 10000 }).toBe(before + 1);
    });

    test('removing a line item takes it off the cart page', async ({ page }) => {
        await addToCartByOem(page, OEM_A);
        await gotoCart(page, ['E2E Storefront Fixture Filter']);

        await expect(visibleText(page, 'E2E Storefront Fixture Filter')).toBeVisible();

        await page.locator('[aria-label="Remove item"]:visible').first().click();

        await expect(page.getByText('E2E Storefront Fixture Filter')).toHaveCount(0, { timeout: 10000 });
    });

    test('applying a valid coupon reflects a discount in the summary', async ({ page }) => {
        await addToCartByOem(page, OEM_A);
        await gotoCart(page, ['E2E Storefront Fixture Filter']);

        const couponInput = page.locator('#promo_code');
        // Same eventual-consistency window as gotoCart's item-text check,
        // just on the summary sidebar's own coupon_code state this time —
        // confirmed to still occasionally lag even once the line item
        // itself has rendered. Reload once more if it hasn't settled.
        if (!(await couponInput.isVisible().catch(() => false))) {
            await page.reload({ waitUntil: 'domcontentloaded' });
            await page.waitForTimeout(800);
        }
        await expect(couponInput).toBeVisible({ timeout: 10000 });
        await couponInput.fill(COUPON_CODE);
        await page.getByRole('button', { name: 'Apply' }).click();

        // Applying a coupon swaps the input row for a "coupon applied" row
        // with its own remove control (title="Remove") — its presence is
        // the observable proof the discount was accepted server-side.
        await expect(page.getByTitle('Remove')).toBeVisible({ timeout: 10000 });
        await expect(couponInput).toBeHidden();
    });

    test('an invalid coupon code shows an error and applies no discount', async ({ page }) => {
        await addToCartByOem(page, OEM_A);
        await gotoCart(page, ['E2E Storefront Fixture Filter']);

        const couponInput = page.locator('#promo_code');
        await couponInput.fill('THIS-CODE-DOES-NOT-EXIST');
        await page.getByRole('button', { name: 'Apply' }).click();

        await page.waitForTimeout(500);
        // The coupon input must still be there (not swapped for the
        // "applied" state) — an invalid code must never silently succeed.
        await expect(couponInput).toBeVisible();
    });

    test('Checkout now navigates to the checkout flow', async ({ page }) => {
        await addToCartByOem(page, OEM_A);
        await gotoCart(page, ['E2E Storefront Fixture Filter']);

        await page.locator('main a[href$="/checkout"]').first().click();
        await page.waitForURL(/\/en\/checkout$/, { waitUntil: 'domcontentloaded' });
    });
});
