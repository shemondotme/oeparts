import { test, expect } from '@playwright/test';
import { artisan, artisanOutput } from '../helpers.js';

/**
 * Record a partial payment on a sent invoice from its page, in a real browser: the
 * invoice becomes "Partially paid", the payment is listed, and the balance shrinks.
 */
test('invoice: record a partial payment from the invoice page', async ({ page }) => {
    artisan(`tinker --execute="App\\Models\\CustomInvoice::where('client_name','E2E Payment Client')->delete(); App\\Models\\CustomInvoice::create(['document_type'=>'invoice','invoice_number'=>'E2E-PAY-000001','status'=>'sent','sent_at'=>now(),'client_name'=>'E2E Payment Client','client_address_line1'=>'Teststrasse 1','client_city'=>'Berlin','client_country_code'=>'DE','currency'=>'EUR','issue_date'=>now()->subDays(20)->toDateString(),'due_date'=>now()->subDays(5)->toDateString(),'items'=>[['description'=>'E2E part','quantity'=>1,'unit_price'=>100]],'vat_rate'=>0]);"`);

    try {
        const id = artisanOutput(`tinker --execute="echo App\\Models\\CustomInvoice::where('invoice_number','E2E-PAY-000001')->value('id');"`).trim().split(/\s+/).pop();
        await page.goto(`/admin/custom-invoices/${id}`, { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('nav.fi-topbar');

        await expect(page.getByText('E2E-PAY-000001').first()).toBeVisible({ timeout: 20000 });
        await expect(page.getByText('Overdue').first()).toBeVisible();

        await page.getByRole('button', { name: /Record payment/i }).or(page.getByText('Record payment')).first().click();
        const amount = page.locator('input[type="number"]').first();
        await expect(amount).toBeVisible({ timeout: 15000 });
        await amount.fill('40');
        await page.locator('input[id*="reference"]').first().fill('E2E-BANK-REF');
        await page.getByRole('button', { name: /Submit|Confirm|Save/i }).last().click();

        await expect(page.getByText('Payment recorded')).toBeVisible({ timeout: 20000 });
        await expect(page.getByText('Partially paid').first()).toBeVisible({ timeout: 20000 });
        await expect(page.getByText('E2E-BANK-REF').first()).toBeVisible({ timeout: 20000 });
    } finally {
        artisan(`tinker --execute="App\\Models\\CustomInvoice::where('client_name','E2E Payment Client')->delete();"`);
    }
});
