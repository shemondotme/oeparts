import { test, expect } from '@playwright/test';

/**
 * Site Copy Library — editing a real ui.* text-override row through its
 * translatable-tabs modal. This edits genuine live storefront copy
 * (cart_apply_coupon, currently "Apply Coupon"), so the test restores the
 * exact original value at the end rather than leaving throwaway data
 * behind, unlike most other fixtures in this suite.
 */

test('site copy library: editing a row updates the value and restores it', async ({ page }) => {
    await page.goto('/admin/settings/site-copy-library', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('nav.fi-topbar');
    await page.waitForTimeout(1000);

    await page.locator('.fi-ta-search-field input').fill('cart_apply_coupon');
    await page.waitForTimeout(1000);

    const row = page.locator('table tbody tr', { hasText: 'cart_apply_coupon' });
    await expect(row).toBeVisible({ timeout: 10000 });
    await expect(row.getByText('Apply Coupon')).toBeVisible();

    await row.getByRole('button', { name: 'Edit', exact: true }).click();
    await page.getByRole('heading', { name: 'Edit: cart_apply_coupon' }).waitFor({ timeout: 10000 });

    const englishField = page.locator('[id="mountedActionSchema0.value.en"]');
    await expect(englishField).toHaveValue('Apply Coupon', { timeout: 10000 });
    await englishField.fill('Apply Coupon E2E TEST');
    await page.getByRole('dialog').getByRole('button', { name: 'Submit', exact: true }).click();
    await page.waitForTimeout(1500);

    await expect(page.getByText('ui.cart_apply_coupon updated')).toBeVisible({ timeout: 10000 });
    await expect(row.getByText('Apply Coupon E2E TEST')).toBeVisible({ timeout: 10000 });

    // Restore the original value
    await row.getByRole('button', { name: 'Edit', exact: true }).click();
    await page.getByRole('heading', { name: 'Edit: cart_apply_coupon' }).waitFor({ timeout: 10000 });
    await page.locator('[id="mountedActionSchema0.value.en"]').fill('Apply Coupon');
    await page.getByRole('dialog').getByRole('button', { name: 'Submit', exact: true }).click();
    await page.waitForTimeout(1500);

    await expect(row.getByText('Apply Coupon', { exact: true })).toBeVisible({ timeout: 10000 });
});
