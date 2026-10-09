import { test, expect } from '@playwright/test';
import { fillText, selectOption, uniqueSuffix } from './helpers.js';
import { artisan } from '../helpers.js';

/**
 * Admin "New order" builds the order from catalog parts: pick a part by OEM number,
 * its price fills in, and subtotal / shipping / VAT / total follow without typing.
 * The unit tests cannot cover the live (browser-side) wiring — in particular the
 * repeater row writing the totals at the form root — so this drives the real page.
 */
test('admin order: a catalog part drives the unit price and the totals', async ({ page }) => {
    const u = uniqueSuffix();

    await page.goto('/admin/orders/create', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('nav.fi-topbar');
    await page.waitForTimeout(1000);

    // 1. Destination + shipping method (the totals depend on both).
    await selectOption(page, 'shipping_country_code', 'DE');
    await selectOption(page, 'shipping_method_id', '1');

    // 2. Search the part in the first (default) item row.
    const row = page.locator('.fi-fo-repeater-item').first();
    await row.locator('.fi-select-input-btn').click();
    const search = row.getByPlaceholder('Start typing to search...');
    await search.fill('ALF-000002');
    const option = row.locator('[role="option"]').first();
    await expect(option).toBeVisible({ timeout: 15000 });
    await option.click();

    // 3. The price came from the catalog and the root totals followed.
    const unitPrice = row.locator('input[id$=".unit_price"]');
    await expect(unitPrice).not.toHaveValue('', { timeout: 15000 });
    const price = parseFloat(await unitPrice.inputValue());
    expect(price).toBeGreaterThan(0);

    const subtotal = page.locator('[id="form.subtotal"]');
    // A number input shows 359.00 as 359, so compare as numbers.
    await expect.poll(async () => parseFloat(await subtotal.inputValue()), { timeout: 15000 }).toBeCloseTo(price, 2);
    await expect.poll(async () => parseFloat(await page.locator('[id="form.grand_total"]').inputValue()), { timeout: 15000 }).toBeGreaterThan(price);
    await expect(page.getByText(/VAT .*% —/).first()).toBeVisible();

    // 4. The auto-calculated figures cannot be typed over unless "Adjust totals manually" is on.
    await expect(subtotal).toHaveJSProperty('readOnly', true);

    // 5. Finish and save.
    await fillText(page, 'shipping_name', `E2E Items Customer ${u}`);
    await fillText(page, 'shipping_address_line1', 'Teststrasse 1');
    await fillText(page, 'shipping_city', 'Berlin');
    await fillText(page, 'shipping_postal_code', '10115');
    await selectOption(page, 'payment_method', 'bank_transfer');
    await page.getByRole('button', { name: 'Create', exact: true }).click();
    await page.waitForURL(/\/admin\/orders\/\d+/, { timeout: 30000 });

    // The sold part is stocked out again so the next test starts clean.
    artisan(`tinker --execute="App\\Models\\Product::where('oem_number','ALF-000002')->update(['is_in_stock'=>true]); App\\Models\\Order::where('shipping_name','like','E2E Items Customer%')->get()->each->forceDelete();"`);
});
