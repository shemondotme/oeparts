import { test, expect } from '@playwright/test';
import { artisan } from '../helpers.js';

/**
 * BulkUpdateProducts — a filter-driven mass-mutation tool operating on an
 * arbitrary matching set (not row-selection like ProductResource's own
 * bulk actions), rebuilt from a pre-Filament tool specifically because it
 * needs to scale to a real ~1M-row production catalog (chunked apply,
 * BulkUpdateLog snapshot for revert, a large-batch confirmation gate).
 * No Filament Schema here — plain native <select>/<input> + wire:model,
 * so no custom-widget interaction quirks to work around.
 *
 * Scoped via oemSearch to exactly one product (id 7, ALF-000001 — the
 * same fixture product-bulk-actions.spec.js uses and leaves out-of-stock)
 * so this test's blast radius is a single row, not "every product
 * matching no filter" — Mark In Stock then Mark Out of Stock again
 * restores the original state.
 */

test.beforeEach(() => {
    // Needs ALF-000001 starting OUT of stock (a real "false → true" change
    // to preview/apply) — confirmed live that a prior run dying before its
    // own restore-to-original-state step at the end (or bulk-update-log.spec.js
    // / product-bulk-actions.spec.js sharing this same fixture product)
    // leaves it stuck in_stock=true, at which point the first preview shows
    // "In Stock → In Stock" instead of the expected "Out of Stock → In Stock".
    artisan(`tinker --execute="App\\Models\\Product::where('oem_number','ALF-000001')->update(['is_in_stock'=>false]);"`);
});

test('bulk update products: filtered preview + apply changes exactly one product', async ({ page }) => {
    await page.goto('/admin/bulk-update-products', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('nav.fi-topbar');
    await page.waitForTimeout(1000);

    await page.locator('input[wire\\:model\\.live\\.debounce\\.400ms="oemSearch"]').fill('ALF-000001');
    await page.locator('select[wire\\:model\\.live="actionType"]').selectOption('stock_in');
    await page.waitForTimeout(500);

    await page.getByRole('button', { name: 'Preview Changes' }).click();
    await page.waitForTimeout(1500);

    await expect(page.getByText('This will affect')).toBeVisible({ timeout: 10000 });
    await expect(page.getByText('ALF-000001')).toBeVisible();
    await expect(page.getByText(/Out of Stock.*In Stock/)).toBeVisible();

    await page.locator('input[type="checkbox"][wire\\:model\\.live="confirmed"]').check();
    await page.getByRole('button', { name: /Apply to \d+ Product/ }).click();
    await page.waitForTimeout(2000);

    await expect(page.getByText('1 products updated').last()).toBeVisible({ timeout: 10000 });

    // Restore original state
    await page.locator('select[wire\\:model\\.live="actionType"]').selectOption('stock_out');
    await page.waitForTimeout(500);
    await page.getByRole('button', { name: 'Preview Changes' }).click();
    await page.waitForTimeout(1500);
    // Confirms the first apply's DB write actually stuck (this preview is
    // freshly queried from the DB) rather than re-checking a second toast,
    // which stacks with the still-lingering first one and is flaky to
    // pin down.
    await expect(page.getByText(/In Stock.*Out of Stock/)).toBeVisible({ timeout: 10000 });
    await page.locator('input[type="checkbox"][wire\\:model\\.live="confirmed"]').check();
    await page.getByRole('button', { name: /Apply to \d+ Product/ }).click();
    await page.waitForTimeout(2000);
    await expect(page.getByText('Internal Server Error')).not.toBeVisible();

    // Final proof the restore landed: a fresh preview now shows no visible
    // change (Out of Stock → Out of Stock) instead of In Stock → Out of Stock.
    await page.getByRole('button', { name: 'Preview Changes' }).click();
    await page.waitForTimeout(1500);
    await expect(page.getByText(/Out of Stock.*Out of Stock/)).toBeVisible({ timeout: 10000 });
});
