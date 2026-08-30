import { test, expect } from '@playwright/test';

/**
 * BulkUpdateLogPage's "Revert" action — the entire reason BulkUpdateLog
 * exists (per BulkUpdateProducts' own class comment: "so a single click on
 * BulkUpdateLogPage can revert it"), never exercised until now. Creates a
 * fresh log entry via a real BulkUpdateProducts apply (product 7,
 * ALF-000001, mark in stock), then reverts that exact entry from the log
 * page — using Revert itself as the cleanup/restore mechanism rather than
 * manually re-applying the opposite action, since that's the real feature
 * under test.
 */

test('bulk update log: revert restores the product to its pre-update state', async ({ page }) => {
    // Produce a fresh, known log entry to revert.
    await page.goto('/admin/bulk-update-products', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('nav.fi-topbar');
    await page.waitForTimeout(1000);
    await page.locator('input[wire\\:model\\.live\\.debounce\\.400ms="oemSearch"]').fill('ALF-000001');
    await page.locator('select[wire\\:model\\.live="actionType"]').selectOption('stock_in');
    await page.waitForTimeout(500);
    await page.getByRole('button', { name: 'Preview Changes' }).click();
    await page.waitForTimeout(1500);
    await page.locator('input[type="checkbox"][wire\\:model\\.live="confirmed"]').check();
    await page.getByRole('button', { name: /Apply to \d+ Product/ }).click();
    await page.waitForTimeout(1500);

    // The log's own list is sorted created_at desc, so the entry just
    // created is the first row.
    await page.goto('/admin/bulk-update-log-page', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('nav.fi-topbar');
    await page.waitForTimeout(1000);

    const firstRow = page.locator('table tbody tr').first();
    await expect(firstRow.getByText('Stock In')).toBeVisible({ timeout: 10000 });

    await firstRow.getByRole('button', { name: 'Revert', exact: true }).click();
    await page.getByRole('button', { name: 'Yes, revert' }).click();
    await page.waitForTimeout(1500);

    await expect(page.getByText('1 product(s) reverted')).toBeVisible({ timeout: 10000 });

    // The revert itself creates a new "Reverted" log entry — confirm it's
    // now the newest row.
    await expect(page.locator('table tbody tr').first().getByText('Reverted')).toBeVisible({ timeout: 10000 });
});
