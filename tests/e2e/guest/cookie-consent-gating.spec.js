import { test, expect } from '@playwright/test';
import { artisan } from '../helpers.js';

/**
 * Phase 11 (Compliance/Legal). Before this fix, GTM/GA4/Facebook Pixel/
 * Crisp were rendered as unconditional, auto-executing <script> tags in
 * layouts/app.blade.php — they fired on the very first page load, before
 * the visitor had even seen the cookie-consent banner, let alone chosen.
 * The banner's Accept/Decline/Customize buttons only ever wrote to
 * localStorage; nothing read those values to gate the trackers. Declining
 * did not stop anything that had already run. This is a real EU ePrivacy
 * Directive violation (prior consent is required before non-essential
 * cookies/trackers are set), not just a missing test — this spec proves
 * the fix (consent-gated loader functions + an orchestrator that only
 * calls them once a choice has actually been made) rather than just
 * asserting the markup looks right.
 *
 * Uses a fake, non-resolving GTM container ID and Facebook pixel ID — the
 * point is only to prove *whether the browser attempts to load the
 * script at all*, not to validate a real GTM/FB account.
 */

const FAKE_GTM_ID = 'GTM-E2ETEST1';
const FAKE_FB_PIXEL_ID = '999900001111';

test.describe('Cookie-consent gating of third-party trackers', () => {
    test.beforeAll(() => {
        artisan(`tinker --execute="App\\Models\\Setting::updateOrCreate(['group'=>'integrations','key'=>'gtm_id'],['value'=>'${FAKE_GTM_ID}','type'=>'string','is_encrypted'=>false]); App\\Models\\Setting::updateOrCreate(['group'=>'integrations','key'=>'fb_pixel_id'],['value'=>'${FAKE_FB_PIXEL_ID}','type'=>'string','is_encrypted'=>false]); app(App\\Services\\SettingsService::class)->forget('integrations');"`);
    });

    test.afterAll(() => {
        artisan(`tinker --execute="App\\Models\\Setting::updateOrCreate(['group'=>'integrations','key'=>'gtm_id'],['value'=>'','type'=>'string','is_encrypted'=>false]); App\\Models\\Setting::updateOrCreate(['group'=>'integrations','key'=>'fb_pixel_id'],['value'=>'','type'=>'string','is_encrypted'=>false]); app(App\\Services\\SettingsService::class)->forget('integrations');"`);
    });

    test.beforeEach(async ({ page }) => {
        // A fresh browser context per test (Playwright default) has no
        // localStorage yet — every test here starts as a genuine
        // first-time visitor with no consent decision recorded.
        await page.goto('/en/', { waitUntil: 'domcontentloaded' });
    });

    test('trackers do not load before any consent choice is made', async ({ page }) => {
        const gtmRequest = page.waitForRequest(
            (req) => req.url().includes('googletagmanager.com/gtm.js'),
            { timeout: 2000 }
        ).catch(() => null);
        const fbRequest = page.waitForRequest(
            (req) => req.url().includes('connect.facebook.net'),
            { timeout: 2000 }
        ).catch(() => null);

        await page.waitForTimeout(2000);

        expect(await gtmRequest).toBeNull();
        expect(await fbRequest).toBeNull();

        // The loader functions must exist (the integration IS configured)
        // — proving they're simply un-called, not that the whole feature
        // silently vanished.
        const loadersExist = await page.evaluate(() => ({
            gtm: typeof window.__oepLoadGTM === 'function',
            fb: typeof window.__oepLoadFBPixel === 'function',
        }));
        expect(loadersExist.gtm).toBe(true);
        expect(loadersExist.fb).toBe(true);
    });

    test('accepting all cookies loads GTM and the Facebook Pixel', async ({ page }) => {
        const gtmRequestPromise = page.waitForRequest((req) => req.url().includes('googletagmanager.com/gtm.js'), { timeout: 10000 });
        const fbRequestPromise = page.waitForRequest((req) => req.url().includes('connect.facebook.net'), { timeout: 10000 });

        await page.getByRole('button', { name: /accept all cookies/i }).click();

        const gtmRequest = await gtmRequestPromise;
        const fbRequest = await fbRequestPromise;

        expect(gtmRequest.url()).toContain(FAKE_GTM_ID);
        expect(fbRequest.url()).toContain('fbevents.js');
    });

    test('declining cookies never loads any tracker, even after the choice is recorded', async ({ page }) => {
        // The button's accessible name comes from its aria-label
        // ("Decline all cookies"), which wins over its visible text
        // ("Decline") in accessible-name computation — an anchored
        // /^decline$/i regex never matches it.
        await page.getByRole('button', { name: /decline all cookies/i }).click();

        const gtmRequest = page.waitForRequest((req) => req.url().includes('googletagmanager.com/gtm.js'), { timeout: 2000 }).catch(() => null);
        const fbRequest = page.waitForRequest((req) => req.url().includes('connect.facebook.net'), { timeout: 2000 }).catch(() => null);
        await page.waitForTimeout(2000);

        expect(await gtmRequest).toBeNull();
        expect(await fbRequest).toBeNull();

        // A returning visit in the same (declined) browser context must
        // stay silent too — this is what actually proves Decline is a
        // durable choice, not just "nothing fired yet this pageview".
        await page.reload({ waitUntil: 'domcontentloaded' });
        const gtmRequest2 = page.waitForRequest((req) => req.url().includes('googletagmanager.com/gtm.js'), { timeout: 2000 }).catch(() => null);
        await page.waitForTimeout(2000);
        expect(await gtmRequest2).toBeNull();
    });

    test('a returning visitor who already accepted gets trackers on the very next page load, no re-click needed', async ({ page }) => {
        // waitForRequest only observes requests made AFTER it's registered
        // — it must be set up before the action that can trigger the
        // request, not after, or a fast-firing request can complete before
        // the listener exists to see it.
        const firstGtmRequestPromise = page.waitForRequest((req) => req.url().includes('googletagmanager.com/gtm.js'), { timeout: 10000 });
        await page.getByRole('button', { name: /accept all cookies/i }).click();
        await firstGtmRequestPromise;

        const gtmRequestPromise = page.waitForRequest((req) => req.url().includes('googletagmanager.com/gtm.js'), { timeout: 10000 });
        await page.reload({ waitUntil: 'domcontentloaded' });
        await expect(gtmRequestPromise).resolves.toBeTruthy();
    });

    test('customizing preferences with analytics off but marketing on only loads the Facebook Pixel', async ({ page }) => {
        await page.getByRole('button', { name: /customize/i }).click();

        // Necessary is always-on and non-interactive; toggle only Marketing on.
        // The real checkbox is visually `sr-only` — its styled toggle-track
        // sibling <div> paints over the same screen area and intercepts a
        // plain click, so click the label (which the input is nested
        // inside, and forwards the click) instead of the hidden input.
        await page.locator('label', { hasText: /marketing/i }).click();

        const fbRequestPromise = page.waitForRequest((req) => req.url().includes('connect.facebook.net'), { timeout: 10000 });
        await page.getByRole('button', { name: /^save$/i }).click();
        await expect(fbRequestPromise).resolves.toBeTruthy();

        // Analytics was left off — GTM must still not have loaded.
        const gtmRequest = page.waitForRequest((req) => req.url().includes('googletagmanager.com/gtm.js'), { timeout: 2000 }).catch(() => null);
        await page.waitForTimeout(2000);
        expect(await gtmRequest).toBeNull();
    });
});
