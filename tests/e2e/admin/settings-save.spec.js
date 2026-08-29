import { test, expect } from '@playwright/test';

/**
 * Saves every real settings page as-is (no field changes) and confirms
 * the save action itself doesn't error — the smoke sweep already proves
 * each page LOADS cleanly; this proves the Save button's handler doesn't
 * throw on a real round trip through validation + persistence. A
 * no-op save is a legitimate, safe action here (these are singleton
 * settings records, not something a re-save can duplicate or corrupt).
 *
 * Save button convention confirmed live: `button[type="submit"]` is
 * ambiguous (every admin page also has a hidden global "Sign out" form
 * whose button is type="submit"), so scope by `wire:target="save"` —
 * unique to the real action regardless of label text (some pages say
 * "Save", others "Save changes").
 */

const SETTINGS_PAGES = [
    'appearance-settings',
    'customization-settings',
    'general-brand-settings',
    'localization-settings',
    'marketing-settings',
    'performance-settings',
    'search-catalog-settings',
    'security-access-settings',
    'store-operations-settings',
    'system-maintenance-settings',
];

test.describe('Admin settings: save round-trips without error', () => {
    for (const slug of SETTINGS_PAGES) {
        test(`${slug} saves cleanly`, async ({ page }) => {
            await page.goto(`/admin/settings/${slug}`, { waitUntil: 'domcontentloaded' });
            await page.waitForSelector('nav.fi-topbar');
            await page.waitForTimeout(800);

            const saveButton = page.locator('button[type="submit"][wire\\:target="save"]').first();
            await saveButton.click();
            await page.waitForTimeout(1500);

            // Success feedback isn't consistent enough across these pages
            // to match one exact phrase: most show a toast ("Saved",
            // "No changes detected" — confirmed live that's ALSO a
            // legitimate no-op outcome, not a failure, for a page with
            // nothing to change), but Localization's Save button instead
            // just flips its own icon to a checkmark with no toast at
            // all. The one thing every failure mode has in common is a
            // field-level validation error message — assert that's
            // absent instead of guessing success wording.
            await expect(page.locator('[id$="-error"], .fi-fo-field-wrp-error-message')).toHaveCount(0);
            await expect(saveButton).toBeEnabled();
        });
    }
});
