import { test, expect } from '@playwright/test';
import { artisanIn, composeIn, isInsecureRemote, settings } from './helpers.js';

/**
 * The installer's "Import an existing site" branch, in a real browser, on a real FRESH install (Gate B, RS-2 of the plan).
 *
 * OPT-IN. This project walks the actual installer and loads a whole shop into an empty database, so it can only ever run against a
 * rehearsal copy of the app (docker/rehearsal/migration/, "mig-b") that has NOT been installed yet. playwright.config.js enables it
 * only with OE_E2E_MIGRATION=1 (and then runs nothing else), and global-setup.js refuses oeparts.test, the dev compose project,
 * and any copy that is already installed. Not part of CI. Run it with:
 *
 *   OE_E2E_MIGRATION=1 OE_E2E_MIGRATION_URL=http://mig-b.test OE_E2E_MIGRATION_PROJECT=mig-b \
 *   OE_E2E_MIGRATION_PACKAGE=rehearsal.oeparts OE_E2E_MIGRATION_PASSPHRASE=... \
 *   OE_E2E_MIGRATION_DB_HOST=mysql OE_E2E_MIGRATION_DB_NAME=mig_b OE_E2E_MIGRATION_DB_USER=... OE_E2E_MIGRATION_DB_PASSWORD=... \
 *   npx playwright test --project=migration
 *
 * What must be in place first: the rehearsal copy is up and uninstalled; the package is in its storage/app/import/ folder (and
 * old enough to count as fully uploaded); the database exists and is empty. Optional: OE_E2E_MIGRATION_COMPOSE_FILE,
 * OE_E2E_MIGRATION_LAUNCH (staging|live), OE_E2E_MIGRATION_SELECTION (e.g. preset:catalog_only), OE_E2E_MIGRATION_DB_PORT,
 * OE_E2E_MIGRATION_DB_CREATE=1.
 *
 * Two configurations, because the installer refuses a passphrase on a connection that is not secure:
 *   - the normal run needs a connection the installer accepts (https, localhost, or OE_INSTALLER_ALLOW_HTTP_PASSPHRASE=true in the
 *     rehearsal's .env) and walks the whole branch to a finished import;
 *   - OE_E2E_MIGRATION_EXPECT_HTTP_REFUSAL=1 runs against plain http WITHOUT the override and proves the refusal, then stops.
 * OE_E2E_MIGRATION_KILL=1 (with OE_E2E_MIGRATION_PHP_SERVICE, default php-fpm) also kills the PHP service in the middle of the data
 * copy and proves the run goes on by itself once the service is back. Rolling back after a kill is the Gate B manual part.
 *
 * Playwright's .fill() can leave a Livewire form's own copy of a value stale; none of the pages walked here is Livewire (plain
 * server-rendered forms), so what is typed is what is sent.
 */
const S = settings();

test.describe.configure({ mode: 'serial' });

/** The browser that owns the wizard: its cookie is what ties the branch to it, so every step uses this one context. */
let ownerContext;
let page;

const fiveMinutes = 5 * 60_000;
const longWait = 60 * 60_000;

test.beforeAll(async ({ browser }) => {
    // A known start: no leftover wizard from an earlier attempt.
    artisanIn(S.project, 'oeparts:migrate:wizard-reset');
    ownerContext = await browser.newContext();
    page = await ownerContext.newPage();
});

test.afterAll(async () => {
    await ownerContext?.close();
});

test('the first step offers two ways to start and says when a package is waiting', async () => {
    await page.goto('/install');

    await expect(page.getByRole('heading', { name: 'How do you want to start?' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Install a new site' })).toBeVisible();
    await expect(page.getByRole('heading', { name: /Import an existing site/ })).toBeVisible();
    await expect(page.getByText(/We found \d+ packages? in /).first()).toBeVisible();
});

test('starting the import takes this browser and goes on to the database step', async () => {
    await page.getByRole('link', { name: 'Import an existing site' }).click();
    await expect(page).toHaveURL(/\/install\/import$/);
    await expect(page.getByRole('heading', { name: 'Import an existing site' })).toBeVisible();

    await page.getByRole('button', { name: 'Start the import' }).click();
    await expect(page).toHaveURL(/\/install\/database$/);
});

test('a second browser is refused by the wizard and can change nothing', async ({ browser }) => {
    const stranger = await browser.newContext();
    try {
        const other = await stranger.newPage();

        for (const path of ['/install/import/package', '/install/import/choose', '/install/import/check']) {
            const response = await other.goto(path);
            expect(response.status(), path).toBe(403);
            await expect(other.getByText('Someone else started this install in another browser.')).toBeVisible();
            await expect(other.locator('form')).toHaveCount(0);
        }

        // Starting a run is a POST with a CSRF token from a session: a bare request has neither (419) or no wizard (403).
        const bare = await stranger.request.post('/install/import/check');
        expect([403, 419]).toContain(bare.status());
    } finally {
        await stranger.close();
    }
});

test('the database step goes on to the package step', async () => {
    await page.goto('/install/database');
    await page.locator('#db_host').fill(S.db.host);
    await page.locator('#db_port').fill(S.db.port);
    await page.locator('#db_name').fill(S.db.name);
    await page.locator('#db_username').fill(S.db.user);
    await page.locator('#db_password').fill(S.db.password);
    if (S.db.create) {
        await page.locator('input[name="create_database"]').check();
    }
    await page.getByRole('button', { name: /Test Connection/ }).click();

    await expect(page).toHaveURL(/\/install\/import\/package$/, { timeout: 2 * 60_000 });
    await expect(page.getByRole('heading', { name: 'Your package' })).toBeVisible();
});

if (S.expectHttpRefusal) {
    test('a passphrase is refused over plain http without the override, and the page points to the command line', async () => {
        expect(isInsecureRemote(S.url), `${S.url} must be plain http on a host that is not the machine itself for this check`).toBe(true);

        await expect(page.locator('#passphrase')).toHaveCount(0);
        await expect(page.getByText('For safety the installer will not accept a passphrase over a connection that is not secure.')).toBeVisible();
        await expect(page.getByText('php artisan oeparts:migrate:import')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Open package' })).toHaveCount(0);
        // Whatever is in the list cannot be chosen either.
        await expect(page.locator('input[name="package"]:not([disabled])')).toHaveCount(0);
    });
} else {
    test('a wrong passphrase is a plain sentence, never a crash, and the right one opens the package', async () => {
        await page.locator(`input[type="radio"][name="package"][value="${S.package}"]`).check();
        await page.locator('#passphrase').fill('definitely not the passphrase');
        await page.getByRole('button', { name: 'Open package' }).click();

        await expect(page.getByText('That passphrase does not open this file.')).toBeVisible();
        await expect(page.locator('#passphrase')).toHaveValue('', 'the typed passphrase is never put back into the form');

        await page.locator(`input[type="radio"][name="package"][value="${S.package}"]`).check();
        await page.locator('#passphrase').fill(S.passphrase);
        await page.getByRole('button', { name: 'Open package' }).click();

        await expect(page.getByRole('heading', { name: 'Package opened' })).toBeVisible();
        await expect(page.getByText(S.package).first()).toBeVisible();
        expect(page.url()).not.toContain(encodeURIComponent(S.passphrase));
    });

    test('step 4 has no default for live or test, and refuses an https address over plain http', async () => {
        await page.getByRole('link', { name: 'Next: choose what to bring' }).click();
        await expect(page).toHaveURL(/\/install\/import\/choose$/);
        await expect(page.getByRole('heading', { name: 'What should we bring over?' })).toBeVisible();

        await expect(page.getByText('Is this your real, live site?')).toBeVisible();
        await expect(page.locator('input[name="launch"]:checked')).toHaveCount(0);
        await expect(page.getByText('What we will adjust for you')).toBeVisible();
        await expect(page.getByText('We check what your choice needs once the database is prepared, and ask you to confirm anything we add.')).toBeVisible();

        const selection = S.selection
            ? page.locator(`input[name="selection"][value="${S.selection}"]`)
            : page.locator('input[name="selection"][value^="preset:"]').first();
        await selection.check();
        await page.locator(`input[name="launch"][value="${S.launch}"]`).check();

        // An https address from a page opened over http would lock the owner out of the site just imported.
        const ownAddress = await page.locator('#site_url').inputValue();
        if (S.url.startsWith('http://')) {
            await page.locator('#site_url').fill(ownAddress.replace(/^http:/, 'https:'));
            await page.getByRole('button', { name: 'Save and continue' }).click();
            await expect(page.getByText('would lock you out')).toBeVisible();
            await expect(page).toHaveURL(/\/install\/import\/choose$/);

            // The form keeps what was chosen; the address goes back to the one in use.
            await expect(page.locator(`input[name="launch"][value="${S.launch}"]`)).toBeChecked();
            await page.locator('#site_url').fill(ownAddress);
        }

        await page.getByRole('button', { name: 'Save and continue' }).click();
        await expect(page).toHaveURL(/\/install\/import\/check$/);
        await expect(page.getByRole('heading', { name: 'Ready to check the package' })).toBeVisible();
    });

    test('pressing check hands the browser to the runner and keeps the token out of the address', async () => {
        await page.getByRole('button', { name: 'Check the package' }).click();

        await page.waitForURL(/\/migrate\/run\/[a-z0-9]+/, { timeout: fiveMinutes });
        // The page moves the token from the address fragment into sessionStorage and removes it from the bar.
        await expect.poll(() => new URL(page.url()).hash, { timeout: 30_000 }).toBe('');
        const keys = await page.evaluate(() => Object.keys(sessionStorage));
        expect(keys.some((key) => key.startsWith('oe_migrate_token_'))).toBe(true);
    });

    test('the runner stops at a confirm panel that lists what it found, and Start waits for every tick', async () => {
        await expect(page.locator('#oe-confirm')).toBeVisible({ timeout: longWait });
        await expect(page.locator('#oe-problem')).toBeHidden();

        const start = page.locator('#oe-start');
        const acks = page.locator('#oe-acks input[type="checkbox"]');
        const added = page.locator('#oe-added');

        await expect(page.locator('#oe-parts')).not.toHaveText('');
        const needsTicks = (await acks.count()) > 0 || (await added.isVisible());
        if (needsTicks) {
            await expect(start).toBeDisabled();
        }

        for (let i = 0; i < (await acks.count()); i++) {
            await acks.nth(i).check();
        }
        if (await added.isVisible()) {
            // "We added Customers because Orders and payments needs them. Please review and confirm."
            await expect(added).toContainText('We added');
            await page.locator('#oe-ack-groups').check();
        }

        await expect(start).toBeEnabled();
    });

    test('the import runs to the end and hands out a signed report', async () => {
        await page.locator('#oe-start').click();

        if (S.kill) {
            // The PHP service dies in the middle of the data copy. The run is on disk, so it must go on by itself once PHP is back.
            await expect(page.locator('#state')).toHaveText(/Copying the data/, { timeout: longWait });
            composeIn(S.project, `kill ${S.phpService}`);
            composeIn(S.project, `up -d ${S.phpService}`);
            await page.waitForTimeout(10_000);
            await page.reload();
        }

        await expect(page.locator('#state')).toHaveText('Done. Your site has been moved.', { timeout: longWait });
        await expect(page.locator('#oe-report')).toBeVisible();

        const href = await page.locator('#oe-report-link').getAttribute('href');
        expect(href).toContain('/install/import/report/');
        expect(href).toContain('signature=');

        await page.locator('#oe-report-link').click();
        await expect(page.getByRole('heading', { name: 'Your site has been imported' })).toBeVisible();
        await expect(page.getByText('Do this before going live')).toBeVisible();
        await expect(page.getByText('Use the admin email and password from your OLD site.')).toBeVisible();
        await expect(page.locator('body')).not.toContainText(S.passphrase);

        // The address works for whoever holds it; without its signature it is refused.
        const bare = await page.request.get(href.split('?')[0]);
        expect(bare.status()).toBe(403);
    });

    test('the imported site answers and the installer is closed', async ({ request }) => {
        for (const path of ['/', '/admin/login']) {
            const response = await request.get(path);
            expect(response.status(), path).toBe(200);
        }

        const installer = await request.get('/install', { maxRedirects: 0 });
        expect([301, 302, 303, 307, 308, 403, 404]).toContain(installer.status());
    });
}
