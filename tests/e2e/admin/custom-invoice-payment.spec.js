import { test, expect } from '@playwright/test';

/**
 * The custom-invoice form now has a Payment section: the method decides which
 * fields appear, and the form says exactly which bank account the invoice would
 * print (or warns that none is configured). The visibility wiring is live
 * (browser-side), so it needs a real page.
 */
test('custom invoice: the payment section shows what will print and reacts to the method', async ({ page }) => {
    await page.goto('/admin/custom-invoices/create', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('nav.fi-topbar');
    await page.waitForTimeout(1000);

    // Default method is a bank transfer: the preview and the account picker are there.
    await expect(page.getByText('This invoice will print')).toBeVisible();
    await expect(page.locator('[id="form.payment_link_url"]')).toHaveCount(0);

    // Switch to an online payment link: the link field appears, the bank preview goes.
    await page.locator('[id="form.payment_method"]').selectOption('payment_link').catch(async () => {
        await page.locator('[id="form.payment_method"]').click();
        await page.getByRole('option', { name: 'Online payment link' }).click();
    });
    await expect(page.locator('[id="form.payment_link_url"]')).toBeVisible({ timeout: 15000 });
    await expect(page.getByText('This invoice will print')).toHaveCount(0);
});

test('bank accounts page loads for an admin who may manage them', async ({ page }) => {
    const response = await page.goto('/admin/invoice-bank-accounts', { waitUntil: 'domcontentloaded' });
    expect(response?.status()).toBe(200);
    await expect(page.getByRole('heading', { name: /Bank Accounts/i }).first()).toBeVisible();
});
