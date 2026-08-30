import { test, expect } from '@playwright/test';

/**
 * ProductResource's bulk actions, built via AdminUi::impactBulkAction() —
 * a different flow from every other action tested elsewhere in this suite:
 * selecting a row surfaces a "Bulk actions" dropdown trigger + a selection
 * indicator bar, and the dropdown items themselves are the same
 * .fi-dropdown-list-item pattern used for row/page ActionGroups.
 *
 * priceIncrease has a real form (percentage TextInput); markInStock has no
 * form but shows an impact-preview table instead (via impactBulkAction's
 * ->modalContent() closure) before the "Yes, proceed" submit button.
 */

test('products: bulk increase price by percentage', async ({ page }) => {
    // Product 7 (ALF-000001) — any product works, this one is stable
    // fixture data confirmed present in this dev DB.
    await page.goto('/admin/products', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('nav.fi-topbar');
    await page.waitForTimeout(1000);

    await page.locator('.fi-ta-search-field input').fill('ALF-000001');
    await page.waitForTimeout(1500);

    const row = page.locator('table tbody tr').first();
    await row.locator('input[type="checkbox"]').check({ timeout: 15000 });
    await expect(page.getByText('1 record selected')).toBeVisible({ timeout: 10000 });

    await page.getByRole('button', { name: 'Bulk actions', exact: true }).click();
    await page.locator('.fi-dropdown-list-item:visible', { hasText: 'Increase Price by %' }).click();

    await page.getByLabel('Percentage Increase').fill('10');
    await page.getByRole('button', { name: 'Yes, proceed' }).click();
    await page.waitForTimeout(1500);

    await expect(page.getByText('Price increased by 10%')).toBeVisible({ timeout: 10000 });
});

test('products: bulk mark in stock shows an impact preview then applies', async ({ page }) => {
    // Product 7 is confirmed is_in_stock = false in this dev DB, so
    // markInStock's summary closure has a real change to preview.
    await page.goto('/admin/products', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('nav.fi-topbar');
    await page.waitForTimeout(1000);

    await page.locator('.fi-ta-search-field input').fill('ALF-000001');
    await page.waitForTimeout(1500);

    const row = page.locator('table tbody tr').first();
    await row.locator('input[type="checkbox"]').check({ timeout: 15000 });
    await expect(page.getByText('1 record selected')).toBeVisible({ timeout: 10000 });

    await page.getByRole('button', { name: 'Bulk actions', exact: true }).click();
    await page.locator('.fi-dropdown-list-item:visible', { hasText: 'Mark In Stock' }).click();

    // impactBulkAction's own preview view — confirms the "before/after"
    // summary actually rendered (heading is "{count} {lcfirst(label)}"),
    // not just a generic confirmation dialog.
    await expect(page.getByText('1 mark in stock')).toBeVisible({ timeout: 10000 });

    await page.getByRole('button', { name: 'Yes, proceed' }).click();
    await page.waitForTimeout(1500);

    await expect(page.getByText(/product.*marked in stock/i)).toBeVisible({ timeout: 10000 });

    // Restore the fixture's original out-of-stock state so this test stays
    // repeatable on the next run instead of permanently flipping product 7.
    await row.locator('input[type="checkbox"]').check({ timeout: 15000 });
    await page.getByRole('button', { name: 'Bulk actions', exact: true }).click();
    await page.locator('.fi-dropdown-list-item:visible', { hasText: 'Mark Out of Stock' }).click();
    await page.getByRole('button', { name: 'Yes, proceed' }).click();
    await page.waitForTimeout(1500);
    await expect(page.getByText(/product.*marked out of stock/i)).toBeVisible({ timeout: 10000 });
});
