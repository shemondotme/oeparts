import { test, expect } from '@playwright/test';
import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

/**
 * System > Migration, two admin flows in a real browser: the Share links tab, and the import wizard's "From this computer" upload
 * for SMALL packages (stock Filament FileUpload, no custom script).
 *
 * WRITTEN, NOT YET RUN. Not part of the opt-in installer project: it needs the normal dev site with a signed-in super admin
 * (tests/e2e/.auth/admin.json from the shared `setup` project), so playwright.config.js has to let a project pick this file up
 * (the `chromium` project currently ignores tests/e2e/migration/). Until then run it with a config that points testDir here:
 *
 *   npx playwright test tests/e2e/migration/admin-share-and-upload.spec.js --project=chromium
 *
 * Needs at least one finished package in the Packages tab for the share-link test (make one with the Export wizard first).
 */

const PAGE = '/admin/system/migration';
const ADMIN_PASSWORD = process.env.OE_E2E_ADMIN_PASSWORD ?? 'superadmin@oeparts';

test.use({ storageState: 'tests/e2e/.auth/admin.json' });

test.describe('Share links tab', () => {
    test('the tab is there and starts with its own empty state or a list', async ({ page }) => {
        await page.goto(PAGE, { waitUntil: 'domcontentloaded' });
        const tab = page.getByRole('tab', { name: 'Share links' });
        await expect(tab).toBeVisible();
        await tab.click();
        await expect(page.locator('.fi-ta, .fi-ta-empty-state').first()).toBeVisible();
    });

    test('a link made from the Packages tab shows up in the Share links tab, and can be taken back with the password', async ({ page }) => {
        await page.goto(PAGE, { waitUntil: 'domcontentloaded' });
        await page.getByRole('tab', { name: 'Packages' }).click();

        const share = page.getByRole('button', { name: 'Share link' }).first();
        await expect(share, 'a finished package must exist in the Packages tab').toBeVisible();
        await share.click();

        const modal = page.locator('.fi-modal-window').last();
        await modal.locator('input[type="password"]').fill(ADMIN_PASSWORD);
        await modal.getByRole('button', { name: /make|share|create/i }).last().click();
        await expect(page.locator('.fi-no-notification').first()).toBeVisible();

        await page.getByRole('tab', { name: 'Share links' }).click();
        const revoke = page.getByRole('button', { name: /revoke|take back/i }).first();
        await expect(revoke).toBeVisible();
        await revoke.click();

        const confirm = page.locator('.fi-modal-window').last();
        await confirm.locator('input[type="password"]').fill(ADMIN_PASSWORD);
        await confirm.getByRole('button', { name: /revoke|take back|confirm/i }).last().click();
        await expect(page.locator('.fi-no-notification').first()).toBeVisible();
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
        await page.goto(PAGE, { waitUntil: 'domcontentloaded' });
        await page.getByRole('tab', { name: 'Import' }).click();
        await page.getByLabel('From this computer').check();
    }

    test('a small package is taken into the import folder and the wizard moves on to the passphrase', async ({ page }) => {
        await chooseComputer(page);
        await page.locator('input[type="file"]').setInputFiles(fakePackage('e2e-small.oeparts', 4096));
        await expect(page.locator('.filepond--item, .fi-fo-file-upload').first()).toBeVisible();
        await page.getByRole('button', { name: 'Next' }).click();
        await expect(page.getByText('The package is in the import folder')).toBeVisible();
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
