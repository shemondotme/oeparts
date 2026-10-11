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
const URL = '/admin/system/cleanup-dashboard';

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

async function open(page) {
    await page.goto(URL, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('nav.fi-topbar');
    await expect(page.getByText('What can be cleaned', { exact: true })).toBeVisible({ timeout: 30000 });
}

/** The targets-table row of one target, found by its visible label. */
function rowOf(page, label) {
    return page.locator('tr').filter({ hasText: label }).first();
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

test('cleanup page: every tab renders without JavaScript errors', async ({ page }, testInfo) => {
    const errors = [];
    page.on('pageerror', (e) => errors.push(e.message));

    await open(page);

    // Headline numbers.
    for (const label of ['Disk used', 'Can be cleaned', 'Automatic cleaning', 'Last cleanup']) {
        await expect(page.getByText(label, { exact: true }).first()).toBeVisible();
    }

    // Each tab shows its own content.
    const tabs = [
        ['Log files', 'Log files'],
        ['Backups', 'Backups on disk'],
        ['History', 'Cleanup history'],
        ['Settings', 'Automatic cleaning & safety settings'],
        ['Tools', 'Dev-only files'],
        ['Tools', 'Database schema audit'],
    ];
    for (const [tab, heading] of tabs) {
        await page.getByRole('tab', { name: tab }).click();
        // A target row can carry the same words ("Cleanup history" is also a prunable target), so look at visible matches only.
        await expect(page.getByText(heading, { exact: true }).locator('visible=true').first()).toBeVisible({ timeout: 20000 });
    }

    await page.screenshot({ path: testInfo.outputPath('cleanup-page.png'), fullPage: true });
    expect(errors).toEqual([]);
});

test('cleanup: preview a real table target is read-only and shows a result', async ({ page }) => {
    await open(page);

    const row = rowOf(page, 'Scheduled task logs');
    await expect(row).toBeVisible({ timeout: 20000 });
    await row.getByRole('button', { name: 'Preview' }).click();

    await expect(page.getByText(/Preview #\d+ — Preview ready/)).toBeVisible({ timeout: 60000 });
    await expect(page.getByText('Result per item')).toBeVisible();
    await page.getByRole('button', { name: 'Dismiss' }).click();
    await expect(page.getByText('Result per item')).toHaveCount(0);
});

test('cleanup: measure, preview, then delete exactly the previewed files — verified', async ({ page }, testInfo) => {
    await open(page);

    await page.getByText('Files & folders', { exact: true }).click();
    const row = rowOf(page, 'E2E scratch files');
    test.skip((await row.count()) === 0, 'the e2e_scratch target is not registered (set OE_CLEANUP_E2E_TARGET=1)');
    await expect(row).toBeVisible({ timeout: 20000 });

    // Measure this one row (row menu -> Re-measure): the three old files are eligible, the fresh one is not.
    await row.locator('.fi-dropdown-trigger button').click();
    await page.getByRole('button', { name: 'Re-measure' }).locator('visible=true').click();
    await expect(rowOf(page, 'E2E scratch files').getByText('3 items qualify').first()).toBeVisible({ timeout: 30000 });

    // Preview
    await rowOf(page, 'E2E scratch files').getByRole('button', { name: 'Preview' }).click();
    await expect(page.getByText(/Preview #\d+ — Preview ready/)).toBeVisible({ timeout: 60000 });
    expect(listFixture().length, 'a preview deletes nothing').toBe(4);

    // Delete exactly what was previewed
    await page.getByRole('button', { name: /Delete these 3 now/ }).click();
    await expect(page.getByText('Clean: E2E scratch files')).toBeVisible({ timeout: 15000 });
    await page.getByRole('button', { name: 'Yes, clean it' }).click();

    // The result reconciles: found 3 = removed 3 + gone elsewhere 0 + left 0. (Waits for THIS run's panel: the
    // history list may already contain a 'Completed & verified' badge from an earlier run.)
    await expect(page.getByText(/Found 3 = removed 3\s+\+ gone elsewhere 0\s+\+ left 0/)).toBeVisible({ timeout: 60000 });
    await expect(page.getByText(/Cleanup #\d+ — Completed & verified/)).toBeVisible();

    // Independently of the page: only the old files are gone, the fresh one stays.
    expect(listFixture()).toEqual(['e2e-fresh.txt']);

    // Polling stopped once the run ended.
    await expect(page.locator('[wire\\:poll\\.2s="pollRun"]')).toHaveCount(0);

    await page.screenshot({ path: testInfo.outputPath('cleanup-result.png'), fullPage: true });

    // It is in the history.
    await page.getByRole('tab', { name: 'History' }).click();
    await expect(page.getByText('Cleanup history', { exact: true }).locator('visible=true').first()).toBeVisible();
    await expect(page.locator('table').getByText('Completed & verified').first()).toBeVisible();
});

test('cleanup: a wrong typed confirmation never starts a real cleanup', async ({ page }) => {
    await open(page);

    // A REAL table target, but the confirmation is never accepted: either the typed-DELETE field is shown and we
    // type the wrong word, or (a small, already-measured amount) only a plain confirmation is shown and we cancel.
    const row = rowOf(page, 'Scheduled task logs');
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

test('cleanup: the keep-days box saves and refuses a value below the minimum', async ({ page }) => {
    await open(page);

    const row = rowOf(page, 'Admin activity log');
    await expect(row).toBeVisible({ timeout: 20000 });
    const input = row.locator('input[type="number"]');
    const original = await input.inputValue();

    // Below the 30-day minimum: refused, the box goes back to the stored value.
    await input.fill('3');
    await input.blur();
    await expect(page.getByText('Not saved').first()).toBeVisible({ timeout: 15000 });

    // Put things back exactly as they were (a valid, unchanged value is simply saved again).
    await page.reload({ waitUntil: 'domcontentloaded' });
    await expect(rowOf(page, 'Admin activity log').locator('input[type="number"]')).toHaveValue(original, { timeout: 20000 });
});
