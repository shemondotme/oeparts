import { test, expect } from '@playwright/test';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

/**
 * RedirectResource's three custom actions, none of which follow the plain
 * resource-form Create/Edit conventions the rest of the admin suite relies
 * on: two are table header actions (not row/page actions), and the third
 * is a real outbound HTTP check rather than a database write.
 *
 * - downloadRedirectTemplate / importCsv are table ->headerActions(), so
 *   they render as plain top-of-table buttons (not the "Actions" page
 *   ActionGroup used by Order/Redirect's own record actions).
 * - testRedirect is a row action, wrapped (with Edit/Delete) inside the
 *   same per-row "..." ActionGroup dropdown pattern used throughout this
 *   suite (AdminUi::recordActionsWithoutView()) — confirmed live via a DOM
 *   dump: label "Test", wire:click="mountAction('testRedirect', ...)".
 * - The import modal's own container never satisfies
 *   `[role="dialog"]:visible` (the same teleported-modal quirk documented
 *   elsewhere in this suite) even though it renders correctly on screen —
 *   wait on the visible "Import CSV" heading instead.
 */

test('redirect: download template streams a real CSV', async ({ page }) => {
    await page.goto('/admin/redirects', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('nav.fi-topbar');
    await page.waitForTimeout(1000);

    const [download] = await Promise.all([
        page.waitForEvent('download'),
        page.getByRole('button', { name: 'Download Template', exact: true }).click(),
    ]);

    expect(download.suggestedFilename()).toBe('redirect-import-template.csv');
});

test('redirect: import CSV uploads a file and queues the job', async ({ page }) => {
    await page.goto('/admin/redirects', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('nav.fi-topbar');
    await page.waitForTimeout(1000);

    await page.getByRole('button', { name: 'Import CSV', exact: true }).click();
    await page.getByRole('heading', { name: 'Import CSV' }).waitFor({ timeout: 10000 });

    await page.locator('input[type="file"]').setInputFiles(
        path.resolve(__dirname, '..', 'fixtures', 'redirect-import-sample.csv')
    );
    // "Upload complete" appears as soon as the dropzone preview renders, but
    // Filament's wire:model binding for the upload syncs slightly after that
    // via its own follow-up request — submitting before it lands throws a
    // spurious "The CSV File field is required" validation error.
    await page.getByText('Upload complete', { exact: true }).waitFor({ timeout: 10000 });
    await page.waitForTimeout(1000);

    await page.getByRole('button', { name: 'Submit', exact: true }).click();
    await page.waitForTimeout(1500);

    await expect(page.getByText('Redirect import queued')).toBeVisible({ timeout: 10000 });
});

test('redirect: test action makes a real check against the destination', async ({ page }) => {
    // testRedirectAction's own ->action() wraps the whole check in a
    // catch (\Throwable), so it always resolves to one of four notification
    // outcomes no matter what the destination is — no need to hunt for a
    // specific "safe" record, the first row in the default-sorted table works.
    await page.goto('/admin/redirects', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('nav.fi-topbar');
    await page.waitForTimeout(1000);

    const groupTrigger = page.locator('table tbody tr').first().locator('.fi-dropdown-trigger button');
    await groupTrigger.first().click();
    await page.waitForTimeout(500);
    await page.locator('.fi-dropdown-list-item:visible', { hasText: 'Test' }).click();

    // The check itself is a real outbound HTTP request the app makes back to
    // itself (5s timeout) — this dev environment's own response times are
    // slow enough (several real seconds, observed directly via curl) that
    // the notification can legitimately take that long to appear. Poll
    // immediately with a generous window rather than burning part of the
    // budget on a blind sleep first.
    const outcome = page.getByText(/Destination responds|Destination itself redirects|Destination responded with HTTP|Could not reach the destination|Internal Server Error/);
    await expect(outcome).toBeVisible({ timeout: 15000 });
    await expect(page.getByText('Internal Server Error')).not.toBeVisible();
});
