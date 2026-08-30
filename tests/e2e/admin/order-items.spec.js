import { test, expect } from '@playwright/test';
import { uniqueSuffix } from './helpers.js';

/**
 * OrderItemsRelationManager — the highest business-value relation manager
 * in the app: every Create/Edit/Delete recalculates the owning order's
 * totals via OrderService::recalculateTotals() (see recalculateOwnerTotals()
 * in the RelationManager itself). Lives only on the Edit Order page (Order
 * ->getRelationManagers() is overridden per-page; ViewOrder shows the same
 * data read-only in its infolist instead).
 */

test('order items: create, edit, and delete a line item recalculates order totals', async ({ page }) => {
    const u = uniqueSuffix();
    const oem = `E2E-ITEM-${u}`;

    await page.goto('/admin/orders/12/edit', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('nav.fi-topbar');
    await page.mouse.wheel(0, 2000);
    await page.getByRole('button', { name: /new order item/i }).waitFor({ timeout: 20000 });

    // Create
    await page.getByRole('button', { name: /new order item/i }).click();
    await page.locator('[id="mountedActionSchema0.oem_number_snapshot"]').waitFor({ timeout: 10000 });
    await page.locator('[id="mountedActionSchema0.oem_number_snapshot"]').fill(oem);
    await page.locator('[id="mountedActionSchema0.manufacturer_snapshot"]').fill('E2E Test Manufacturer');
    await page.locator('[id="mountedActionSchema0.condition_snapshot"]').fill('New');
    await page.locator('[id="mountedActionSchema0.quantity"]').fill('2');
    await page.locator('[id="mountedActionSchema0.unit_price"]').fill('10.00');
    await page.getByRole('button', { name: 'Create', exact: true }).click();
    await page.waitForTimeout(1500);

    await expect(page.getByText('Order totals recalculated')).toBeVisible({ timeout: 10000 });
    const row = page.locator('table tbody tr', { hasText: oem });
    await expect(row).toBeVisible({ timeout: 10000 });
    await expect(row.getByText('20.00', { exact: false })).toBeVisible();

    // Edit — bump quantity, confirm the line total (and the recalculation
    // notification) reflect the new value.
    await row.getByRole('button', { name: 'Edit', exact: true }).click();
    await page.locator('[id="mountedActionSchema0.quantity"]').waitFor({ timeout: 10000 });
    await page.locator('[id="mountedActionSchema0.quantity"]').fill('3');
    await page.getByRole('dialog').getByRole('button', { name: 'Save changes', exact: true }).click();
    await page.waitForTimeout(1500);

    await expect(page.getByText('Order totals recalculated')).toBeVisible({ timeout: 10000 });
    await expect(row.getByText('30.00', { exact: false })).toBeVisible();

    // Delete
    await row.getByRole('button', { name: 'Delete', exact: true }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Delete', exact: true }).click();
    await page.waitForTimeout(1500);

    await expect(page.getByText('Order totals recalculated')).toBeVisible({ timeout: 10000 });
    await expect(page.locator('table tbody tr', { hasText: oem })).not.toBeVisible();
});
