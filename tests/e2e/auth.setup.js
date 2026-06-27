import { test as setup } from '@playwright/test';
import { loginAsSuperAdmin } from './helpers.js';

const authFile = 'tests/e2e/.auth/admin.json';

// Logs in once via the real login UI (not an API shortcut — this is the
// only place besides the dedicated logout test that exercises the login
// form) and saves the session so every other test starts already
// authenticated.
setup('authenticate as super_admin', async ({ page }) => {
    // This local XAMPP environment is occasionally much slower than usual
    // under sustained load (observed: the login page itself sometimes
    // takes >60s to become interactive) — give this load-bearing step
    // generous headroom rather than letting the whole suite fail to start.
    setup.setTimeout(120000);
    await loginAsSuperAdmin(page);
    await page.context().storageState({ path: authFile });
});
