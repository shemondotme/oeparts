import { defineConfig, devices } from '@playwright/test';

/**
 * Tests run against the real local dev server, not a Node dev server —
 * there is no webServer entry here. Start the app yourself before running
 * `npm run test:e2e` (this environment runs it via Docker Sail —
 * `docker compose up` — serving oeparts.test; an XAMPP/Apache vhost also
 * works as long as it serves the same host). Fixture-seeding specs use
 * the `artisan()` helper (tests/e2e/helpers.js), which shells out through
 * `docker compose exec laravel.test` rather than a bare `php` on PATH.
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
        {
            // Unauthenticated storefront flows (search, product detail,
            // cross-reference navigation) — no `setup` dependency and no
            // storageState, unlike `chromium` above, since these must be
            // exercised as a real anonymous visitor would see them.
            name: 'guest',
            testDir: './tests/e2e/guest',
            use: {
                ...devices['Desktop Chrome'],
                viewport: { width: 1280, height: 800 },
            },
        },
        // Cross-browser / responsive coverage (Phase 8, bulletproof-testing
        // initiative). Scoped to checkout-bank-transfer.spec.js specifically
        // — the "browse -> cart -> checkout" purchase journey, this site's
        // single most business-critical flow — rather than the entire guest
        // suite: running every a11y/locale/responsive sweep across three
        // more engines would multiply this suite's runtime for near-zero
        // marginal signal (those already assert on rendered DOM state, which
        // doesn't meaningfully differ by engine the way real user-input
        // event handling and payment-form interaction can).
        {
            name: 'firefox',
            testDir: './tests/e2e/guest',
            testMatch: /checkout-bank-transfer\.spec\.js/,
            use: {
                ...devices['Desktop Firefox'],
                viewport: { width: 1280, height: 800 },
            },
        },
        {
            name: 'webkit',
            testDir: './tests/e2e/guest',
            testMatch: /checkout-bank-transfer\.spec\.js/,
            use: {
                ...devices['Desktop Safari'],
                viewport: { width: 1280, height: 800 },
            },
        },
        {
            // Real device profile (viewport + touch + UA), Chromium engine —
            // deliberately not a 4th rendering engine; WebKit above already
            // covers the "Safari" case that matters most for a real mobile
            // user (iOS has no non-WebKit browsers). Pixel 5 pairs the most
            // common real-world mobile form factor with the engine behind
            // the majority of actual mobile traffic (Chrome on Android).
            name: 'mobile',
            testDir: './tests/e2e/guest',
            testMatch: /checkout-bank-transfer\.spec\.js/,
            use: {
                ...devices['Pixel 5'],
            },
        },
    ],
});
