import { test, expect } from '@playwright/test';

/**
 * Anonymous-visitor account lifecycle: register -> dashboard -> add
 * address -> update settings -> logout -> log back in. Selectors are
 * taken from resources/views/components/modals/auth-modal.blade.php,
 * resources/views/components/account/shell.blade.php, and the
 * resources/views/frontend/account/* views.
 *
 * OTP verification is globally disabled in this dev environment
 * (confirmed: OtpService::enabled() === false), so both register and
 * login complete in one round trip with no code-entry step — register
 * immediately logs the new user in and redirects to the dashboard
 * (auth-modal.blade.php's submitRegister(): `window.location.href = dashboardUrl`
 * whenever `requires_otp` comes back false).
 *
 * Each test in this file uses its own freshly-registered account (a
 * unique email per test run) rather than sharing one across tests, since
 * this `guest` Playwright project gives every test its own isolated
 * browser context/session by default.
 */

function uniqueEmail(label) {
    return `e2e-${label}-${Date.now()}-${Math.floor(Math.random() * 100000)}@example.com`;
}

/**
 * Fills and submits the login form on whatever page the auth modal is
 * currently open on. submitLogin() reloads the CURRENT page on success
 * (`window.location.reload()`, not a URL change), so the click needs to
 * be paired with a navigation wait, not just its own (fast) actionability
 * — otherwise the resulting reload can race the test's next action.
 */
async function loginViaModal(page, email, password) {
    await page.locator('#login-email').fill(email);
    await page.locator('#login-password').fill(password);
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 15000 }),
        page.getByRole('button', { name: /^sign in$/i }).click(),
    ]);
}

async function registerNewAccount(page, email, password = 'E2eTest!Passw0rd') {
    await page.goto('/en/', { waitUntil: 'domcontentloaded' });
    await page.getByRole('button', { name: 'Sign in' }).first().click();
    await page.locator('#auth-tab-register').click();

    await page.locator('#reg-name').fill('Playwright Visitor');
    await page.locator('#reg-email').fill(email);
    await page.locator('#reg-password').fill(password);
    await page.locator('#reg-confirm').fill(password);
    await page.locator('#reg-terms').check();

    await page.getByRole('button', { name: /create account/i }).click();
    await page.waitForURL(/\/en\/account\/dashboard$/, { waitUntil: 'domcontentloaded', timeout: 15000 });
}

test.describe('Account lifecycle', () => {
    test('registering a new account signs the visitor in and lands on the dashboard', async ({ page }) => {
        await registerNewAccount(page, uniqueEmail('register'));

        await expect(page.locator('h1')).toBeVisible();
    });

    test('signing out then logging back in with the same credentials works', async ({ page }) => {
        const email = uniqueEmail('login');
        const password = 'E2eTest!Passw0rd';
        await registerNewAccount(page, email, password);

        await page.getByRole('button', { name: /sign out/i }).click();
        await page.waitForURL(/\/en\/?$/, { waitUntil: 'domcontentloaded', timeout: 15000 });
        // The logout redirect's own navigation can still be settling —
        // an immediate second goto() intermittently aborts it
        // (net::ERR_ABORTED). Let the page fully finish loading first.
        await page.waitForLoadState('load');

        // Dashboard must now be unreachable without re-authenticating.
        await page.goto('/en/account/dashboard', { waitUntil: 'domcontentloaded' });
        await expect(page).not.toHaveURL(/\/account\/dashboard$/);

        await page.goto('/en/', { waitUntil: 'domcontentloaded' });
        await page.getByRole('button', { name: 'Sign in' }).first().click();
        await loginViaModal(page, email, password);

        await page.goto('/en/account/dashboard', { waitUntil: 'domcontentloaded' });
        await expect(page).toHaveURL(/\/account\/dashboard$/);
    });

    test('adding a new address shows it on the addresses page', async ({ page }) => {
        await registerNewAccount(page, uniqueEmail('address'));

        await page.goto('/en/account/addresses/create', { waitUntil: 'domcontentloaded' });

        await page.locator('#first_name').fill('Playwright');
        await page.locator('#last_name').fill('Visitor');
        await page.locator('#address_line_1').fill('Teststrasse 1');
        await page.locator('#city').fill('Berlin');
        await page.locator('#postal_code').fill('10115');
        await page.locator('#country_code').selectOption('DE');

        await page.getByRole('button', { name: /save address/i }).click();

        await page.waitForURL(/\/en\/account\/addresses$/, { waitUntil: 'domcontentloaded', timeout: 15000 });
        await expect(page.getByText('Teststrasse 1')).toBeVisible();
    });

    test('a freshly registered account has no orders and shows the empty-state CTA', async ({ page }) => {
        await registerNewAccount(page, uniqueEmail('orders'));

        await page.goto('/en/account/orders', { waitUntil: 'domcontentloaded' });

        // The CTA links to route('frontend.search.console', ...), which
        // resolves to the plain "/parts" path — it contains no literal
        // "search" substring, so match on the resolved href instead.
        await expect(page.locator('a[href$="/parts"]').first()).toBeVisible();
    });

    test('changing the account password succeeds and the new password logs in', async ({ page }) => {
        // This test does the most round trips of the suite (register,
        // settings update, logout, login) — give it more than the
        // default 60s.
        test.setTimeout(90000);

        const email = uniqueEmail('pwchange');
        const oldPassword = 'E2eTest!Passw0rd';
        const newPassword = 'E2eTest!Passw0rdNew1';
        await registerNewAccount(page, email, oldPassword);

        await page.goto('/en/account/settings', { waitUntil: 'domcontentloaded' });
        await page.locator('#settings-tab-security').click();

        await page.locator('#current_password').fill(oldPassword);
        await page.locator('#new_password').fill(newPassword);
        await page.locator('#new_password_confirmation').fill(newPassword);
        await page.getByRole('button', { name: /update password/i }).click();

        await page.waitForTimeout(1000);

        await page.getByRole('button', { name: /sign out/i }).click();
        await page.waitForURL(/\/en\/?$/, { waitUntil: 'domcontentloaded', timeout: 15000 });
        await page.waitForLoadState('load');

        await page.getByRole('button', { name: 'Sign in' }).first().click();
        await loginViaModal(page, email, newPassword);

        await page.goto('/en/account/dashboard', { waitUntil: 'domcontentloaded' });
        await expect(page).toHaveURL(/\/account\/dashboard$/);
    });
});
