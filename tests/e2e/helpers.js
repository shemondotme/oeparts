import { execSync } from 'node:child_process';

// Matches database/seeders/AdminSeeder.php's super_admin row exactly —
// confirmed live against this dev DB. The previous hardcoded credentials
// here (admin@oeparts.test / Admin@123456) matched neither that email's
// real password nor a super_admin role (that email seeds as plain
// 'admin', a lower-privilege role) — login was silently broken.
const ADMIN_EMAIL = 'superadmin@oeparts.test';
const ADMIN_PASSWORD = 'superadmin@oeparts';

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
 *
 * Waits on `nav.fi-topbar`, not a `#dashboard-canvas` id — that id doesn't
 * exist anywhere in the current codebase (confirmed via grep), so every
 * test relying on it was failing outright before this fix. The topbar
 * also renders immediately as part of the page shell, unlike the
 * dashboard's widgets (each runs its own DB queries on load), so this is
 * both correct and faster.
 */
export async function loginAsSuperAdmin(page) {
    await page.goto('/admin/login', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('input[type="email"]');

    await page.locator('input[type="email"]').fill(ADMIN_EMAIL);
    await page.locator('input[type="password"]').fill(ADMIN_PASSWORD);
    await page.waitForTimeout(300);
    await page.locator('button[type="submit"]').click();

    await page.waitForURL(/\/admin$/, { waitUntil: 'domcontentloaded', timeout: 45000 });
    await page.waitForSelector('nav.fi-topbar', { timeout: 45000 });
}
