import { test, expect } from '@playwright/test';
import { artisan } from '../helpers.js';

/**
 * A quotation is converted to an invoice from the list's action menu in a real
 * browser: the action appears for a quotation, asks for confirmation, creates a
 * numbered draft invoice and lands on its edit page.
 */
test('quotation: convert to invoice from the list menu', async ({ page }) => {
    artisan(`tinker --execute="App\\Models\\CustomInvoice::where('client_name','E2E Quote Client')->delete(); App\\Models\\CustomInvoice::create(['document_type'=>'quote','invoice_number'=>'E2E-QUO-000001','status'=>'sent','client_name'=>'E2E Quote Client','client_address_line1'=>'Teststrasse 1','client_city'=>'Berlin','client_country_code'=>'DE','currency'=>'EUR','issue_date'=>now()->toDateString(),'due_date'=>now()->addDays(30)->toDateString(),'items'=>[['description'=>'E2E part','quantity'=>1,'unit_price'=>100]],'vat_rate'=>21]);"`);

    try {
        await page.goto('/admin/custom-invoices?tableSearch=E2E-QUO-000001', { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('nav.fi-topbar');
        const row = page.locator('tr', { hasText: 'E2E-QUO-000001' });
        await expect(row).toBeVisible({ timeout: 20000 });
        await expect(row.getByText('Quotation').first()).toBeVisible();

        // Open the row's action menu and convert.
        await row.locator('.fi-dropdown-trigger, button[aria-label*="ctions"]').last().click();
        await page.getByRole('menuitem', { name: /Convert to invoice/i }).or(page.getByText('Convert to invoice')).first().click();
        await page.getByRole('button', { name: /Confirm|Yes|Submit/i }).first().click();

        // Lands on the new draft's edit page.
        await page.waitForURL(/\/admin\/custom-invoices\/\d+\/edit/, { timeout: 30000 });
        await expect(page.getByText(/Edit INV-/)).toBeVisible({ timeout: 15000 });
    } finally {
        artisan(`tinker --execute="App\\Models\\CustomInvoice::where('client_name','E2E Quote Client')->update(['parent_id'=>null]); App\\Models\\CustomInvoice::where('client_name','E2E Quote Client')->delete();"`);
    }
});
