import { test, expect } from '@playwright/test';

/**
 * AdminUi::exportCsvBulkAction() is shared by 39 resources. Its own code
 * comments document two crash classes already fixed there: a translatable
 * (array-cast) column crashing (string) coercion, and a BackedEnum column
 * doing the same. These two resources exercise exactly those cases through
 * a real browser click-select-export-download — Product's manufacturer.name
 * is translatable, Redirect's type is a BackedEnum.
 */

test('products: export CSV downloads a real file with no crash', async ({ page }) => {
    await page.goto('/admin/products', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('nav.fi-topbar');
    await page.waitForTimeout(1000);

    await page.locator('table tbody tr').first().locator('input[type="checkbox"]').check({ timeout: 15000 });
    await expect(page.getByText('1 record selected')).toBeVisible({ timeout: 10000 });

    await page.getByRole('button', { name: 'Bulk actions', exact: true }).click();
    const [download] = await Promise.all([
        page.waitForEvent('download'),
        page.locator('.fi-dropdown-list-item:visible', { hasText: 'Export Products' }).click(),
    ]);

    expect(download.suggestedFilename()).toMatch(/^export-.*\.csv$/);
    await expect(page.getByText('Internal Server Error')).not.toBeVisible();
});

test('redirects: export CSV downloads a real file with no crash', async ({ page }) => {
    await page.goto('/admin/redirects', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('nav.fi-topbar');
    await page.waitForTimeout(1000);

    await page.locator('table tbody tr').first().locator('input[type="checkbox"]').check({ timeout: 15000 });
    await expect(page.getByText('1 record selected')).toBeVisible({ timeout: 10000 });

    await page.getByRole('button', { name: 'Bulk actions', exact: true }).click();
    const [download] = await Promise.all([
        page.waitForEvent('download'),
        page.locator('.fi-dropdown-list-item:visible', { hasText: 'Export Redirects' }).click(),
    ]);

    expect(download.suggestedFilename()).toMatch(/^export-.*\.csv$/);
    await expect(page.getByText('Internal Server Error')).not.toBeVisible();
});
