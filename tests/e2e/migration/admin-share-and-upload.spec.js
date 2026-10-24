import { test, expect } from '@playwright/test';
import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

/**
 * System > Migration, two admin flows in a real browser: the Share links tab, and the import wizard's "From this computer" upload
 * for SMALL packages (stock Filament FileUpload, no custom script).
 *
 Run against the imported rehearsal site (Gate B) on 2026-10-24. Not part of the opt-in installer project: it needs a site with a signed-in super admin
 * (tests/e2e/.auth/admin.json from the shared `setup` project), so playwright.config.js has to let a project pick this file up
 * (the `chromium` project currently ignores tests/e2e/migration/). Until then run it with a config that points testDir here:
 *
 *   npx playwright test tests/e2e/migration/admin-share-and-upload.spec.js --project=chromium
 *
 * Needs at least one finished package in the Packages tab for the share-link test (make one with the Export wizard first).
 */

const PAGE = '/admin/system/migration';
const ADMIN_PASSWORD = process.env.OE_E2E_ADMIN_PASSWORD ?? 'superadmin@oeparts';

test.use({ storageState: process.env.OE_E2E_ADMIN_STATE ?? 'tests/e2e/.auth/admin.json' });

// A Livewire page draws its tabs and wizard with script, so on a slow machine they appear a little after the page loads.
test.use({ actionTimeout: 30_000 });
expect.configure({ timeout: 30_000 });

test.describe('Share links tab', () => {
    // The page holds several tables and dialog containers (one per tab, most of them hidden), so every locator is scoped to the
    // VISIBLE one: `.last()` of all dialogs, or the first table, is usually not the one on screen.
    const visibleDialog = (page) => page.locator('.fi-modal-window:visible, [role=dialog]:visible').first();
    const visibleTable = (page) => page.locator('table:visible').first();

    test('the tab is there and starts with its own empty state or a list', async ({ page }) => {
        await page.goto(PAGE, { waitUntil: 'networkidle' });
        const tab = page.getByRole('tab', { name: 'Share links' });
        await expect(tab).toBeVisible();
        await tab.click();
        await expect(page.getByText(/one-time download links made on this site/i)).toBeVisible();
    });

    test('a link made from the Packages tab shows up in the Share links tab, and can be taken back with the password', async ({ page }) => {
        await page.goto(PAGE, { waitUntil: 'networkidle' });
        await page.getByRole('tab', { name: 'Packages' }).click();

        // Row actions are links inside the table row, not buttons.
        const share = visibleTable(page).getByText('Share link', { exact: true }).first();
        await expect(share, 'a finished package must exist in the Packages tab').toBeVisible();
        await share.click();

        // A WRONG password changes nothing and the dialog stays open.
        await visibleDialog(page).locator('input[type="password"]').fill('definitely-not-the-password');
        await visibleDialog(page).getByRole('button', { name: /Make the link/i }).click();
        await expect(visibleDialog(page)).toBeVisible();

        // The right one makes the link: shown once, with the command to run on the other site.
        await visibleDialog(page).locator('input[type="password"]').fill(ADMIN_PASSWORD);
        await visibleDialog(page).getByRole('button', { name: /Make the link/i }).click();
        await expect(page.getByText(/oeparts:migrate:import --url=/)).toBeVisible();

        await page.goto(PAGE, { waitUntil: 'networkidle' });
        await page.getByRole('tab', { name: 'Share links' }).click();
        await expect(visibleTable(page).getByText('Active').first()).toBeVisible();

        // Taking it back needs the password too, and a wrong one leaves the link active.
        await visibleTable(page).getByText('Revoke', { exact: true }).first().click();
        await visibleDialog(page).locator('input[type="password"]').fill('definitely-not-the-password');
        await visibleDialog(page).getByRole('button', { name: /Revoke/i }).last().click();
        await expect(visibleTable(page).getByText('Active').first()).toBeVisible();

        await visibleDialog(page).locator('input[type="password"]').fill(ADMIN_PASSWORD);
        await visibleDialog(page).getByRole('button', { name: /Revoke/i }).last().click();
        await page.goto(PAGE, { waitUntil: 'networkidle' });
        await page.getByRole('tab', { name: 'Share links' }).click();
        await expect(visibleTable(page).getByText('Revoked').first()).toBeVisible();
        await expect(visibleTable(page).getByText('Revoke', { exact: true })).toHaveCount(0);
    });
});

test.describe('Import wizard: From this computer', () => {
    function fakePackage(name, bytes, magic = 'OEPARTS1') {
        const dir = mkdtempSync(join(tmpdir(), 'oeparts-upload-'));
        const file = join(dir, name);
        writeFileSync(file, Buffer.concat([Buffer.from(magic), Buffer.alloc(bytes, 7)]));
        return file;
    }

    async function chooseComputer(page) {
        await page.goto(PAGE, { waitUntil: 'networkidle' });
        await page.getByRole('tab', { name: 'Import' }).click();
        await page.getByRole('radio', { name: /From this computer/ }).check();
    }

    // The server checks the whole package (header, table of contents), not just its first bytes, so this needs a REAL small package:
    // export one with `php artisan oeparts:migrate:export --preset=catalog_only` and point OE_E2E_SMALL_PACKAGE at the file.
    test('a small package is taken into the import folder and the wizard moves on to the passphrase', async ({ page }) => {
        const real = process.env.OE_E2E_SMALL_PACKAGE;
        test.skip(!real, 'set OE_E2E_SMALL_PACKAGE to a real .oeparts file of a few MB at most');

        await chooseComputer(page);
        await page.locator('input[type="file"]').first().setInputFiles(real);
        await expect(page.getByText(/Upload complete/i).first()).toBeVisible();
        await page.getByRole('button', { name: /^Next/i }).click();

        // On to the second step: the file is where the wizard looks for it and now asks for its passphrase.
        await expect(page.getByText(/Open it/i).first()).toBeVisible();
        await expect(page.getByText(/Choose the package file first/i)).toHaveCount(0);
    });

    test('a file that is not a package is refused and nothing is kept', async ({ page }) => {
        await chooseComputer(page);
        await page.locator('input[type="file"]').setInputFiles(fakePackage('e2e-bad.oeparts', 2048, 'NOTAPKG1'));
        await page.getByRole('button', { name: 'Next' }).click();
        await expect(page.locator('.fi-fo-field-wrp-error-message, .fi-fo-field-wrp [data-validation-error]').first()).toBeVisible();
        await expect(page.getByText('The package is in the import folder')).toHaveCount(0);
    });

    test('a file over the limit is refused with the way to use instead', async ({ page }) => {
        await chooseComputer(page);
        await page.locator('input[type="file"]').setInputFiles(fakePackage('e2e-big.oeparts', 13 * 1024 * 1024));
        await expect(page.getByText(/FTP|SFTP|command line|link/i).first()).toBeVisible();
    });

    test('Next without a file asks for one', async ({ page }) => {
        await chooseComputer(page);
        await page.getByRole('button', { name: 'Next' }).click();
        await expect(page.getByText('Choose the package file first')).toBeVisible();
    });
});
