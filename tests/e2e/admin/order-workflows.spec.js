import { test, expect } from '@playwright/test';
import { artisan, artisanOutput } from '../helpers.js';

/**
 * Order-specific header actions — the highest-business-value custom
 * workflows in the admin panel, registered in
 * OrderResource/Pages/ViewOrder.php's getHeaderActions(). Each is
 * visible only for specific order/payment states (per its own
 * ->visible() closure), so every test targets a real order already in
 * that exact state — queried live from this dev DB, not fixture data.
 *
 * Two things confirmed live while writing this that don't match the
 * resource-form conventions used elsewhere in this suite:
 *   - changeStatus and "Generate Invoice PDF" are NOT standalone
 *     buttons here — ViewOrder.php wraps them (with toggleUrgent and
 *     addNote) inside one "Actions" ActionGroup dropdown, same pattern
 *     as the table-row ActionGroups in custom-actions.spec.js. Only
 *     confirmPayment/capturePayment are standalone visible buttons.
 *   - Action-modal schema fields do NOT use the `form.{key}` id
 *     convention crud-create/crud-edit rely on — that's specific to a
 *     Resource's own Create/Edit form. getByLabel(...) is what actually
 *     works here (confirmed via custom-actions.spec.js's RefundRequest
 *     reject test already).
 *
 * capturePayment (Airwallex) is deliberately not covered — it makes a
 * real outbound API call with no local sandbox available, the same
 * limitation as the storefront's card-payment checkout path.
 */

test('order: changeStatus transitions a pending order to processing', async ({ page }) => {
    // Order 12's status is a one-time "pending" fixture state this test
    // itself consumes (pending -> processing) — a full-suite re-run
    // against the same DB found it already "processing" from the FIRST
    // run and failed outright. Force it back before every run instead of
    // assuming a fresh seed.
    artisan(`tinker --execute="App\\Models\\Order::find(12)->update(['status'=>'pending']);"`);

    await page.goto('/admin/orders/12', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('nav.fi-topbar');
    await page.waitForTimeout(1000);

    await page.getByRole('button', { name: 'Actions', exact: true }).first().click();
    await page.locator('.fi-dropdown-list-item:visible', { hasText: 'Change Status' }).click();

    // Unlike most Selects elsewhere in this app, "New Status" here
    // renders as a genuinely native <select> (confirmed live) — a
    // plain selectOption() call works directly.
    await page.getByLabel('New Status').selectOption('processing');

    await page.getByLabel('Status Note').fill('E2E test: moving to processing.');
    await page.getByRole('button', { name: 'Submit' }).click();
    await page.waitForTimeout(1500);

    await expect(page.getByText('Order status updated')).toBeVisible({ timeout: 10000 });
});

test('order: confirmPayment marks a pending bank-transfer order as paid', async ({ page }) => {
    // Hardcoding a specific order id (previously 28) drifts the same way
    // ResolveE2eEditTargets's own doc comment already documents for other
    // resources — confirmed live that order 28 no longer exists at all in
    // this dev DB (visiting it 404s, which is what actually produced the
    // reported "waiting for nav.fi-topbar" timeout below, not a state
    // problem). Resolve a real order id at run time instead, and force it
    // into the exact state this test needs — a "clean" pre-payment
    // bank-transfer order where the Processing transition is valid — same
    // one-time-fixture problem as the changeStatus test above: this test's
    // own success consumes that state (confirmPayment's own visible() gate
    // requires payment_status === Pending), so it must be forced back
    // before every run regardless. Excludes order 12 (changeStatus test's
    // own fixture above) so the two tests never fight over the same row.
    const orderId = artisanOutput(`tinker --execute="\$o = App\\Models\\Order::where('id','!=',12)->orderBy('id')->first(); \$o->update(['status'=>'pending','payment_status'=>'pending','payment_method'=>'bank_transfer']); echo \$o->id;"`).trim();

    await page.goto(`/admin/orders/${orderId}`, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('nav.fi-topbar');
    await page.waitForTimeout(1000);

    await page.getByRole('button', { name: 'Confirm Payment' }).click();

    await page.getByLabel('Transaction Reference').fill('E2E-TEST-REF-001');
    await page.getByRole('button', { name: 'Confirm', exact: true }).click();
    await page.waitForTimeout(1500);

    await expect(page.getByText('Payment confirmed')).toBeVisible({ timeout: 10000 });
});

test('order: confirmPayment fails gracefully instead of a 500 when the order cannot move to Processing', async ({ page }) => {
    // Regression test for a real bug: an order that's bank_transfer +
    // payment pending, but whose order status is already refund_requested.
    // Confirming payment always tries to transitionStatus(..., Processing,
    // ...) underneath (PaymentService::confirmBankTransferPayment), which
    // OrderService rejects for a refund_requested order — and the action's
    // try/catch only caught \RuntimeException, not the \InvalidArgumentException
    // transitionStatus() actually throws, so this used to render a raw
    // Laravel "Internal Server Error" page instead of a notification.
    //
    // Previously hardcoded to order 5, which held this state at seed time
    // but drifted (confirmed live: now processing/paid, a real order that
    // completed normally) — same fixture-drift problem as the test above.
    // Force a real order into the required combo directly rather than
    // relying on any specific row happening to still be in it.
    const orderId = artisanOutput(`tinker --execute="\$o = App\\Models\\Order::where('id','!=',12)->orderBy('id')->first(); \$o->update(['status'=>'refund_requested','payment_status'=>'pending','payment_method'=>'bank_transfer']); echo \$o->id;"`).trim();

    await page.goto(`/admin/orders/${orderId}`, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('nav.fi-topbar');
    await page.waitForTimeout(1000);

    await page.getByRole('button', { name: 'Confirm Payment' }).click();
    await page.getByRole('button', { name: 'Confirm', exact: true }).click();
    await page.waitForTimeout(1500);

    await expect(page.getByText('Internal Server Error')).not.toBeVisible();
    await expect(page.getByText('Confirmation failed')).toBeVisible({ timeout: 10000 });
});

test('order: generate invoice PDF opens a real invoice for a paid order', async ({ page }) => {
    // Order 1 confirmed paid — "Generate Invoice PDF" lives inside the
    // "Actions" dropdown and navigates straight to the invoice route
    // (no modal).
    await page.goto('/admin/orders/1', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('nav.fi-topbar');
    await page.waitForTimeout(1000);

    await page.getByRole('button', { name: 'Actions', exact: true }).first().click();

    const [response] = await Promise.all([
        page.waitForResponse((r) => r.url().includes('/orders/') && r.url().includes('/invoice')),
        page.locator('.fi-dropdown-list-item:visible', { hasText: 'Generate Invoice PDF' }).click(),
    ]);

    expect(response.status()).toBeLessThan(400);
});
