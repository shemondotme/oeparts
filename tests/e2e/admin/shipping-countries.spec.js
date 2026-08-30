import { test, expect } from '@playwright/test';

/**
 * ShippingZoneResource's Countries relation manager — focused on its one
 * genuinely custom piece of business logic: "Add All EU/EEA Countries"
 * (ViesService::getEuCountries()), a plain header Action (not a Filament
 * BulkAction) that inserts every EU/EEA country not already on the zone in
 * one click, and is idempotent on a second run ("Nothing to add"). The
 * manual single-country "Add Country" form uses a reactive searchable
 * Select (52 options) that proved too flaky to drive reliably here even
 * with a forced click — visually confirmed working correctly by hand
 * (screenshot showed the country_name auto-fill firing correctly), so
 * this is a test-tooling gap, not a product one; left uncovered rather
 * than shipping a coin-flip test.
 *
 * Zone 3 ("E2E Edited ...", reused from crud-edit.spec.js) starts with
 * zero countries, confirmed live — this test restores that state at the
 * end so it stays repeatable.
 */

test('shipping zone countries: Add All EU/EEA Countries is idempotent', async ({ page }) => {
    await page.goto('/admin/shipping-zones/3/edit', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('nav.fi-topbar');
    await page.mouse.wheel(0, 2000);
    await page.getByRole('button', { name: 'Add All EU/EEA Countries', exact: true }).waitFor({ timeout: 20000 });

    // First run adds a real batch
    await page.getByRole('button', { name: 'Add All EU/EEA Countries', exact: true }).click();
    await page.getByRole('button', { name: 'Confirm', exact: true }).click();
    await page.waitForTimeout(2000);
    await expect(page.getByText(/\d+ countries added/)).toBeVisible({ timeout: 10000 });
    await expect(page.getByText('Germany')).toBeVisible({ timeout: 10000 });

    // Second run is a no-op — proves the "skip existing" logic actually works
    await page.getByRole('button', { name: 'Add All EU/EEA Countries', exact: true }).click();
    await page.getByRole('button', { name: 'Confirm', exact: true }).click();
    await page.waitForTimeout(2000);
    await expect(page.getByText('Nothing to add')).toBeVisible({ timeout: 10000 });

    // Cleanup — restore zone 3 to zero countries for the next run. 32 EU
    // countries exceed one page, so the header checkbox alone only grabs
    // the current page; Filament's "Select all N" link expands to every
    // matching record across pages.
    await page.locator('th.fi-ta-selection-cell input[type="checkbox"]').check({ timeout: 10000 });
    const selectAllLink = page.getByRole('button', { name: /select all \d+/i });
    if (await selectAllLink.count() > 0) {
        await selectAllLink.click();
    }
    await page.getByRole('button', { name: 'Bulk actions', exact: true }).click();
    await page.locator('.fi-dropdown-list-item:visible', { hasText: 'Delete' }).click();
    await page.waitForTimeout(1500);
    // A plain getByRole('button', { name: 'Delete' }) is genuinely
    // ambiguous here — the zone's own page-header "Delete" action (which
    // deletes the WHOLE ZONE) shares the exact same accessible name and
    // is still in the DOM even though scrolled out of view. Scope to the
    // confirm dialog's own distinct heading to guarantee this can never
    // click the wrong one.
    const confirmDialog = page.locator('[role="dialog"]', { hasText: 'Delete selected Shipping Countries' });
    await confirmDialog.getByRole('button', { name: 'Delete', exact: true }).click();
    await page.waitForTimeout(2000);
    await expect(page.locator('table tbody tr')).toHaveCount(0, { timeout: 10000 });
});
