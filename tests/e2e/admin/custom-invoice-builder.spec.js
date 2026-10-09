import { test, expect } from '@playwright/test';
import { fillText, selectOption } from './helpers.js';
import { artisan, artisanOutput } from '../helpers.js';

/**
 * The custom-invoice builder in a real browser: a catalog part fills the line
 * (part number, description, price), the live totals follow the VAT treatment,
 * the invoice saves and its PDF renders.
 */
test('custom invoice: catalog part, export treatment, live totals, saved PDF', async ({ page }) => {
    await page.goto('/admin/custom-invoices/create', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('nav.fi-topbar');
    await page.waitForTimeout(1000);

    // Client
    await fillText(page, 'client_name', 'E2E Invoice Client');
    await fillText(page, 'client_address_line1', 'Teststrasse 1');
    await fillText(page, 'client_city', 'Tokyo');
    await selectOption(page, 'client_country_code', 'JP');

    // Pick a catalog part in the first line.
    const row = page.locator('.fi-fo-repeater-item').first();
    await row.locator('.fi-select-input-btn').first().click();
    await row.getByPlaceholder('Start typing to search...').fill('ALF-000002');
    const option = row.locator('[role="option"]').first();
    await expect(option).toBeVisible({ timeout: 15000 });
    await option.click();

    // The part filled the line.
    await expect(row.locator('input[id$=".part_number"]')).toHaveValue('ALF-000002', { timeout: 15000 });
    const price = parseFloat(await row.locator('input[id$=".unit_price"]').inputValue());
    expect(price).toBeGreaterThan(0);

    // Standard VAT: the live totals include VAT, so TOTAL is above the price.
    const preview = page.getByText(/TOTAL/).first();
    await expect(preview).toBeVisible({ timeout: 15000 });
    await expect(page.getByText(/VAT \d+(\.\d+)?%/).first()).toBeVisible({ timeout: 15000 });

    // Export: the VAT part of the preview disappears.
    await page.locator('[id="form.vat_treatment"]').selectOption('export').catch(async () => {
        await page.locator('[id="form.vat_treatment"]').click();
        await page.getByRole('option', { name: /Export outside the EU/ }).click();
    });
    await expect(page.getByText(/VAT \d+(\.\d+)?%/)).toHaveCount(0, { timeout: 15000 });
    await expect(page.locator('[id="form.vat_exemption_note"]')).toBeVisible();

    await page.getByRole('button', { name: 'Create', exact: true }).click();
    await page.waitForURL(/\/admin\/custom-invoices$/, { timeout: 30000 });
    await expect(page.getByText('E2E Invoice Client').first()).toBeVisible();

    // The PDF renders (a real DomPDF render, not just a template string).
    const id = artisan_id();
    const pdf = await page.request.get(`/admin/custom-invoices/${id}/pdf`);
    expect(pdf.status()).toBe(200);
    expect(pdf.headers()['content-type']).toContain('application/pdf');

    artisan(`tinker --execute="App\\Models\\CustomInvoice::where('client_name','E2E Invoice Client')->delete();"`);
});

function artisan_id() {
    // Look up the id of the invoice this test just created.
    return artisanOutput(`tinker --execute="echo App\\Models\\CustomInvoice::where('client_name','E2E Invoice Client')->latest('id')->value('id');"`).trim().split(/\s+/).pop();
}
