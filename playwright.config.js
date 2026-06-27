import { defineConfig, devices } from '@playwright/test';

/**
 * Tests run against the real local dev server (XAMPP/Apache vhost), not a
 * Node dev server — there is no webServer entry here. Start the app
 * yourself (the vhost at APP_URL) before running `npm run test:e2e`.
 *
 * Auth is handled once by the `setup` project (tests/e2e/auth.setup.js),
 * which logs in via the real UI and saves the session to
 * tests/e2e/.auth/admin.json. The `chromium` project depends on `setup`
 * and reuses that storageState — individual tests/`beforeEach` hooks just
 * navigate, they don't re-submit the login form every time.
 */
export default defineConfig({
    testDir: './tests/e2e',
    // The local XAMPP dashboard is genuinely slow on first paint (every
    // widget runs its own queries on load, confirmed via tracing — a
    // bare login-to-dashboard round trip alone takes ~25-30s here), so
    // the 30s Playwright default is too tight once a test's own
    // assertions run after that load.
    timeout: 60000,
    // Confirmed empirically: running 2+ workers against this local XAMPP
    // server causes real, reproducible request contention — several tests
    // (outside-click dismissal, notifications panel, dashboard load at
    // narrow viewports) flake under 2 workers and pass reliably every time
    // under 1. This is the local PHP/MySQL setup's ceiling, not a test or
    // app bug — raise this if/when running against a beefier environment.
    workers: 1,
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    reporter: 'list',
    use: {
        baseURL: 'http://oeparts.test',
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
    },

    projects: [
        {
            name: 'setup',
            testMatch: /auth\.setup\.js/,
        },
        {
            name: 'chromium',
            use: {
                ...devices['Desktop Chrome'],
                viewport: { width: 1280, height: 800 },
                storageState: 'tests/e2e/.auth/admin.json',
            },
            dependencies: ['setup'],
        },
    ],
});
