import { test, expect } from '@playwright/test';

/**
 * SEO Control Center — two pieces never exercised beyond the generic
 * settings-save.spec.js check: the live template-preview placeholders
 * (search_results_title_template has 'live' => true specifically so its
 * Placeholder recomputes on keystroke, interpolating {oem}/{site}/etc.
 * against a real sample product — id 7, ALF-000001, the first active
 * product), and the "Regenerate sitemap now" queued-job action.
 */

test('seo control center: search results title template preview updates live', async ({ page }) => {
    await page.goto('/admin/settings/seo-settings', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('nav.fi-topbar');
    await page.waitForTimeout(1000);

    await page.getByRole('tab', { name: 'Search Results' }).click();
    await page.waitForTimeout(500);

    const titleField = page.locator('[id="form.search_results_title_template.en"]');
    await titleField.waitFor({ timeout: 10000 });
    await titleField.fill('Buy OEM Part {oem} — From €{min} | {site}');
    await page.waitForTimeout(1000);

    await expect(page.getByText(/Buy OEM Part ALF-000001/)).toBeVisible({ timeout: 10000 });
});

test('seo control center: regenerate sitemap queues the job', async ({ page }) => {
    await page.goto('/admin/settings/seo-settings', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('nav.fi-topbar');
    await page.waitForTimeout(1000);

    await page.getByRole('tab', { name: /sitemap/i }).click();
    await page.waitForTimeout(500);

    await page.getByRole('button', { name: 'Regenerate sitemap now', exact: true }).click();
    await page.getByRole('button', { name: 'Confirm', exact: true }).click();
    await page.waitForTimeout(1500);

    await expect(page.getByText('Sitemap regeneration requested')).toBeVisible({ timeout: 10000 });
});
