import { test, expect } from '@playwright/test';

test('review: approving a pending review updates its status', async ({ page }) => {
    await page.goto('/admin/content/reviews', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('nav.fi-topbar');
    await page.waitForTimeout(1500);

    // Every row's View/Edit/Approve/Reject/Delete actions are grouped
    // behind one "..." trigger (AdminUi::recordActions() wraps them all
    // in a single Filament ActionGroup) — open the first row's group,
    // then click "Approve" inside whichever dropdown is currently
    // :visible (all rows' dropdown panels exist in the DOM at once,
    // teleported and CSS-hidden until opened).
    const groupTrigger = page.locator('table tbody tr').first().locator('.fi-dropdown-trigger button');
    await groupTrigger.first().click();

    await page.locator('.fi-dropdown-list-item:visible', { hasText: 'Approve' }).click();
    await page.waitForTimeout(1000);

    await expect(page.getByText('Review approved')).toBeVisible({ timeout: 5000 });
});

test('refund request: rejecting a pending request requires a reason and updates its status', async ({ page }) => {
    // RefundRequestResource::rejectAction() only shows this button while
    // status === Pending — id 1 is confirmed pending in this dev DB.
    await page.goto('/admin/refund-requests/1', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('nav.fi-topbar');
    await page.waitForTimeout(1000);

    await page.getByRole('button', { name: 'Reject', exact: true }).click();

    const reasonField = page.getByLabel('Rejection Reason');
    await expect(reasonField).toBeVisible({ timeout: 10000 });
    await reasonField.fill('E2E test rejection — part was installed, outside return window.');

    await page.getByRole('button', { name: 'Submit' }).click();
    await page.waitForTimeout(1500);

    await expect(page.getByText('Refund request rejected')).toBeVisible({ timeout: 10000 });
    await expect(page.getByText('Rejected', { exact: true }).first()).toBeVisible();
});

test('refund request: approving a pending request updates its status', async ({ page }) => {
    // id 2 is confirmed pending — a separate record from the reject test
    // above so the two don't race for the same row.
    await page.goto('/admin/refund-requests/2', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('nav.fi-topbar');
    await page.waitForTimeout(1000);

    await page.getByRole('button', { name: 'Approve', exact: true }).click();

    // approveAction() has requiresConfirmation() but no custom schema —
    // a plain Filament confirm dialog with a "Confirm" submit button.
    await page.getByRole('button', { name: 'Confirm' }).click();
    await page.waitForTimeout(1500);

    await expect(page.getByText('Refund approved')).toBeVisible({ timeout: 10000 });
    await expect(page.getByText('Approved', { exact: true }).first()).toBeVisible();
});
