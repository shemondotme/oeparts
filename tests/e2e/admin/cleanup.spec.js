import { test, expect } from '@playwright/test';
import { execSync } from 'node:child_process';

/**
 * System -> Cleanup, driven through the real UI.
 *
 * SAFETY: every destructive step here runs ONLY against the test-only `e2e_scratch` target — a private folder
 * (storage/app/private/e2e-cleanup/) that this spec fills with its OWN fixture files — never against real logs,
 * backups or tables. The target is registered only when OE_CLEANUP_E2E_TARGET is set (see config/cleanup.php),
 * so the spec skips itself on an environment without it. The real DB-table targets are only ever PREVIEWED
 * (read-only). No "older than N days" sweep is run against any real directory.
 *
 * Files are created/removed through `docker compose exec` as the `sail` user (the same user the web server runs as),
 * not root, so storage/ ownership is never disturbed.
 */

const DIR = '/var/www/html/storage/app/private/e2e-cleanup';

function sail(command) {
    return execSync(`docker compose exec -T -u sail laravel.test sh -c "${command}"`, { encoding: 'utf8' });
}

function resetEngineState() {
    // A leftover active run (e.g. from an interrupted earlier attempt) would block a new one: close it.
    // The code goes in through stdin so no shell has a chance to mangle the namespace backslashes.
    execSync('docker compose exec -T laravel.test php artisan tinker', {
        input: "App" + String.fromCharCode(92) + "Models" + String.fromCharCode(92) + "CleanupRun::query()->active()->update(['status' => 'failed', 'active_key' => null]);\n",
        stdio: ['pipe', 'ignore', 'ignore'],
    });
}

function seedFixture() {
    sail(`rm -rf ${DIR} && mkdir -p ${DIR} && cd ${DIR} && for i in 1 2 3; do echo old-$i > e2e-old-$i.txt; touch -d '3 days ago' e2e-old-$i.txt; done && echo fresh > e2e-fresh.txt`);
}

function listFixture() {
    return sail(`ls ${DIR} 2>/dev/null || true`).trim().split('\n').filter(Boolean).sort();
}

test.describe.configure({ mode: 'serial' });

test.beforeAll(() => {
    resetEngineState();
    seedFixture();
});

test.afterAll(() => {
    sail(`rm -rf ${DIR}`);
    resetEngineState();
});

test('cleanup page: every section renders without JavaScript errors', async ({ page }, testInfo) => {
    const errors = [];
    page.on('pageerror', (e) => errors.push(e.message));

    await page.goto('/admin/system/cleanup-dashboard', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('nav.fi-topbar');

    for (const heading of ['Disk & overview', 'Logs & history', 'Log files', 'Backups & updates', 'Other files & folders', 'Cleanup history', 'Automatic cleaning & safety settings', 'Dev-only files', 'Database schema audit']) {
        await expect(page.getByText(heading, { exact: true }).first()).toBeVisible({ timeout: 20000 });
    }

    await page.screenshot({ path: testInfo.outputPath('cleanup-page.png'), fullPage: true });
    expect(errors).toEqual([]);
});

test('cleanup: preview a real table target is read-only and shows a result', async ({ page }) => {
    await page.goto('/admin/system/cleanup-dashboard', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('nav.fi-topbar');

    const row = page.locator('[wire\\:key="oc-row-cron_logs"]');
    await expect(row).toBeVisible({ timeout: 20000 });
    await row.getByRole('button', { name: 'Preview' }).click();

    await expect(page.getByText('Preview ready', { exact: true }).first()).toBeVisible({ timeout: 60000 });
    await expect(page.getByText('Scheduled task logs').first()).toBeVisible();
    await page.getByRole('button', { name: 'Dismiss' }).click();
});

test('cleanup: measure, preview, then delete exactly the previewed files — verified', async ({ page }, testInfo) => {
    await page.goto('/admin/system/cleanup-dashboard', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('nav.fi-topbar');

    const row = page.locator('[wire\\:key="oc-row-e2e_scratch"]');
    test.skip((await row.count()) === 0, 'the e2e_scratch target is not registered (set OE_CLEANUP_E2E_TARGET=1)');

    // Measure this one row: the three old files are eligible, the fresh one is not.
    await row.getByTitle('Re-measure this one').click();
    await expect(row.getByText('~3').first()).toBeVisible({ timeout: 30000 });

    // Preview
    await row.getByRole('button', { name: 'Preview' }).click();
    await expect(page.getByText('Preview ready', { exact: true }).first()).toBeVisible({ timeout: 60000 });
    expect(listFixture().length, 'a preview deletes nothing').toBe(4);

    // Delete exactly what was previewed
    await page.getByRole('button', { name: /Delete these 3 now/ }).click();
    await expect(page.getByText('Clean: E2E scratch files')).toBeVisible({ timeout: 15000 });
    await page.getByRole('button', { name: 'Yes, clean it' }).click();

    // The result table reconciles: found 3 = removed 3 + gone elsewhere 0 + left 0. (Waits for THIS run's panel:
    // the history list below may already contain a 'Completed & verified' pill from an earlier run.)
    await expect(page.getByText(/Found 3 = removed 3\s+\+ gone elsewhere 0\s+\+ left 0/)).toBeVisible({ timeout: 60000 });
    await expect(page.locator('.oc-card').filter({ hasText: /Cleanup #\d+/ }).getByText('Completed & verified').first()).toBeVisible();

    // Independently of the page: only the old files are gone, the fresh one stays.
    expect(listFixture()).toEqual(['e2e-fresh.txt']);

    // Polling stopped once the run ended.
    await expect(page.locator('[wire\\:poll\\.2s="pollRun"]')).toHaveCount(0);

    await page.screenshot({ path: testInfo.outputPath('cleanup-result.png'), fullPage: true });

    // It is in the history.
    await expect(page.getByText('Cleanup history').first()).toBeVisible();
    await expect(page.locator('table').getByText('Completed & verified').first()).toBeVisible();
});

test('cleanup: a wrong typed confirmation never starts a real cleanup', async ({ page }) => {
    await page.goto('/admin/system/cleanup-dashboard', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('nav.fi-topbar');

    // A REAL table target, but the confirmation is never accepted: either the typed-DELETE field is shown and we
    // type the wrong word, or (a small, already-measured amount) only a plain confirmation is shown and we cancel.
    const row = page.locator('[wire\\:key="oc-row-cron_logs"]');
    await expect(row).toBeVisible({ timeout: 20000 });
    await row.getByRole('button', { name: 'Clean now' }).click();
    await expect(page.getByText('Clean: Scheduled task logs')).toBeVisible({ timeout: 15000 });

    const typed = page.getByLabel('Type DELETE to confirm');
    if (await typed.count()) {
        await typed.fill('delete');
        await page.getByRole('button', { name: 'Yes, clean it' }).click();
        await expect(page.getByText('Type DELETE in capital letters to confirm.')).toBeVisible({ timeout: 10000 });
    } else {
        await page.getByRole('button', { name: 'Cancel' }).click();
    }

    // No run was created by any of that.
    await expect(page.getByText(/Cleanup #\d+/)).toHaveCount(0);
});
