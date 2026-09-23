import { test, expect } from '@playwright/test';
import { artisan } from '../helpers.js';

test.beforeEach(() => {
    // These three tests each consume a one-time "pending" state (approve/
    // reject actions are only visible while status === Pending) — running
    // the suite a second time against the same DB left every one of them
    // permanently non-reproducible (confirmed live: a full-suite re-run
    // failed all three because review 2 / refund 1 / refund 2 were no
    // longer pending from the FIRST run). Force each fixture record back
    // to its required starting state before every run instead of assuming
    // a fresh seed.
    //
    // The review fixture in particular used to be an UPDATE-only reset
    // (assumed the row already existed) — confirmed live during a
    // frontend/UX audit that this shared dev DB's "E2E Playwright
    // Reviewer" row didn't exist at all (product_reviews was completely
    // empty), so the update was a silent no-op and the test then timed
    // out searching for a table row that was never there. updateOrCreate
    // makes the fixture self-healing regardless of whether a prior run
    // (or something else entirely) ever created it.
    artisan(`tinker --execute="App\\Models\\Review::updateOrCreate(['reviewer_name'=>'E2E Playwright Reviewer'],['product_id'=>App\\Models\\Product::query()->value('id'),'title'=>'E2E test review','comment'=>'Seeded by custom-actions.spec.js beforeEach.','rating'=>5,'status'=>'pending']);"`);
    // Same self-healing rationale as the Review fixture above — confirmed
    // live that this dev DB's refund_requests table can end up completely
    // empty (not just stale), which made the old find(N)->update(...) form
    // fatal (calling ->update() on null). Both tests navigate straight to
    // /admin/refund-requests/{1,2} by URL, so the ids themselves must exist,
    // not just some pending row — updateOrCreate(['id'=>N], ...) guarantees
    // that regardless of whether a prior run (or a seeder) ever created them.
    artisan(`tinker --execute="\$orderId = App\\Models\\Order::query()->value('id'); \$userId = App\\Models\\User::query()->value('id'); App\\Models\\RefundRequest::updateOrCreate(['id'=>1],['order_id'=>\$orderId,'user_id'=>\$userId,'reason'=>'E2E test refund reason.','amount_requested'=>10.00,'status'=>'pending','admin_note'=>null,'processed_at'=>null]); App\\Models\\RefundRequest::updateOrCreate(['id'=>2],['order_id'=>\$orderId,'user_id'=>\$userId,'reason'=>'E2E test refund reason 2.','amount_requested'=>10.00,'status'=>'pending','admin_note'=>null,'processed_at'=>null]);"`);
});

test('review: approving a pending review updates its status', async ({ page }) => {
    await page.goto('/admin/content/reviews', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('nav.fi-topbar');
    await page.waitForTimeout(1000);

    await page.locator('.fi-ta-search-field input').fill('E2E Playwright Reviewer');
    // Confirmed live (via the underlying Review row, product_id, and status
    // all being correct when this intermittently failed) that this is the
    // debounced search request not always landing within a flat 1000ms wait
    // in this dev environment, not a missing/broken fixture — wait for the
    // row itself instead of a blind timeout, matching the more generous
    // budgets the other two tests in this file already use.
    const row = page.locator('table tbody tr', { hasText: 'E2E Playwright Reviewer' });
    await expect(row).toBeVisible({ timeout: 10000 });

    // Every row's View/Edit/Approve/Reject/Delete actions are grouped
    // behind one "..." trigger (AdminUi::recordActions() wraps them all
    // in a single Filament ActionGroup) — open the first row's group,
    // then click "Approve" inside whichever dropdown is currently
    // :visible (all rows' dropdown panels exist in the DOM at once,
    // teleported and CSS-hidden until opened).
    const groupTrigger = row.locator('.fi-dropdown-trigger button');
    await groupTrigger.first().click();

    await page.locator('.fi-dropdown-list-item:visible', { hasText: 'Approve' }).click();
    await page.waitForTimeout(1000);

    await expect(page.getByText('Review approved')).toBeVisible({ timeout: 10000 });
});

test('refund request: rejecting a pending request requires a reason and updates its status', async ({ page }) => {
    // RefundRequestResource::rejectAction() only shows this button while
    // status === Pending — forced back to pending in beforeEach above.
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
    // id 2 is a separate record from the reject test above so the two
    // don't race for the same row — forced back to pending in beforeEach.
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
