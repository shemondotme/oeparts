import { execSync } from 'node:child_process';

const ADMIN_EMAIL = 'admin@oeparts.test';
const ADMIN_PASSWORD = 'Admin@123456';

/**
 * Runs an `artisan` command against the app this suite's baseURL actually
 * serves. This dev environment runs entirely under Docker Sail (no host
 * PHP on PATH — confirmed: a bare `php artisan ...` fails immediately
 * with "'php' is not recognized" in both Git Bash and PowerShell) — every
 * e2e fixture command must go through the laravel.test container, not a
 * host-side `php` binary.
 */
export function artisan(command) {
    execSync(`docker compose exec -T laravel.test php artisan ${command}`, { stdio: 'inherit' });
}

/**
 * Logs in via the real login UI on the given page and waits for the
 * dashboard to render. Shared by auth.setup.js (the one-time shared
 * session) and any test that needs its own isolated, freshly-authenticated
 * session (e.g. the logout test — see topbar.spec.js for why).
 */
export async function loginAsSuperAdmin(page) {
    await page.goto('/admin/login', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('input[type="email"]');

    await page.locator('input[type="email"]').fill(ADMIN_EMAIL);
    await page.locator('input[type="password"]').fill(ADMIN_PASSWORD);
    await page.waitForTimeout(300);
    await page.locator('button[type="submit"]').click();

    await page.waitForURL(/\/admin$/, { waitUntil: 'domcontentloaded', timeout: 45000 });
    await page.waitForSelector('#dashboard-canvas', { timeout: 45000 });
}
