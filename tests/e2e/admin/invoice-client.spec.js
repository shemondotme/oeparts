import { test, expect } from '@playwright/test';
import { fillText } from './helpers.js';
import { artisan } from '../helpers.js';

/**
 * Saved clients on the invoice form in a real browser: picking one fills the whole
 * Client section (including the region), and the VAT-number check button answers.
 * A non-EU number is used for the check so the test never depends on the VIES service.
 */
test('invoice form: a saved client fills the client section; the VAT check answers', async ({ page }) => {
    artisan(`tinker --execute="App\\Models\\InvoiceClient::where('company','E2E Client Co')->delete(); App\\Models\\InvoiceClient::create(['name'=>'E2E Contact','company'=>'E2E Client Co','email'=>'e2e-client@example.com','address_line1'=>'2-2-24 Ogami','city'=>'Hiratsuka','state'=>'Kanagawa','postal_code'=>'2540012','country_code'=>'JP','currency'=>'USD']);"`);

    try {
        await page.goto('/admin/custom-invoices/create', { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('nav.fi-topbar');
        await page.waitForTimeout(1000);

        // Pick the saved client.
        const picker = page.locator('.fi-fo-field', { hasText: 'Saved client' }).locator('.fi-select-input-btn').first();
        await picker.click();
        await page.getByPlaceholder('Start typing to search...').first().fill('E2E Client');
        await page.locator('[role="option"]', { hasText: 'E2E Client Co' }).first().click();

        // The section was filled from the client, region included.
        await expect(page.locator('[id="form.client_company"]')).toHaveValue('E2E Client Co', { timeout: 15000 });
        await expect(page.locator('[id="form.client_state"]')).toHaveValue('Kanagawa');
        await expect(page.locator('[id="form.client_city"]')).toHaveValue('Hiratsuka');

        // VAT check on a non-EU number answers immediately with an explanation.
        await fillText(page, 'client_vat_number', 'JP1234567890');
        await page.getByRole('button', { name: /Check in VIES/i }).or(page.locator('[title*="VIES"], [aria-label*="VIES"]')).first().click();
        await expect(page.getByText(/Only EU VAT numbers can be checked/)).toBeVisible({ timeout: 15000 });
    } finally {
        artisan(`tinker --execute="App\\Models\\InvoiceClient::where('company','E2E Client Co')->delete();"`);
    }
});
