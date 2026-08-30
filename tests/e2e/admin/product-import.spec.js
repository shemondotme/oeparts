import { test, expect } from '@playwright/test';
import path from 'path';
import { fileURLToPath } from 'url';
import fs from 'fs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

/**
 * ProductImport — a chunked, resumable, poll-driven FSM (ImportManager),
 * previously only smoke-tested (page loads). Builds a real one-row CSV
 * using this dev DB's real manufacturer/condition slugs, uploads it via
 * the page's plain native Livewire file input (no FilePond here, unlike
 * RedirectResource's importCsvAction), and watches wire:poll.1s drive it
 * to completion.
 */

test('product import: uploads a CSV and creates a real product', async ({ page }) => {
    const u = Date.now();
    const oem = `E2E-IMPORT-${u}`;
    const csvPath = path.resolve(__dirname, '..', 'fixtures', `product-import-${u}.csv`);
    fs.writeFileSync(
        csvPath,
        `oem_number,manufacturer_slug,condition_slug,price,is_in_stock\n${oem},alfa-romeo,used,49.99,1\n`
    );

    try {
        await page.goto('/admin/product-import', { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('nav.fi-topbar');
        await page.getByRole('button', { name: 'Start Import' }).waitFor({ timeout: 15000 });

        await page.locator('input[type="file"]').setInputFiles(csvPath);
        await page.waitForTimeout(1500);
        await page.getByRole('button', { name: 'Start Import' }).click();
        await page.waitForTimeout(1000);

        await expect(page.getByText('Last Import')).toBeVisible({ timeout: 30000 });
        await expect(page.getByText(/1 created/)).toBeVisible({ timeout: 10000 });
        await expect(page.getByText(/0 errors/)).toBeVisible();
    } finally {
        fs.unlinkSync(csvPath);
    }
});
