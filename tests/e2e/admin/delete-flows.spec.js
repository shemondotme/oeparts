import { test, expect } from '@playwright/test';
import { uniqueSuffix, fillText, submitCreate } from './helpers.js';

/**
 * Single-record and bulk delete had zero coverage anywhere in this suite —
 * every other test either reads, creates, or edits. Uses throwaway FAQ
 * records (created here, not real fixture data) so deleting them is safe;
 * FAQ is the simplest resource in the app (one required field) specifically
 * to keep the create step from being its own source of flakiness.
 */

test('faq: single delete removes exactly that record', async ({ page }) => {
    const u = uniqueSuffix();
    const question = `E2E delete-me FAQ ${u}?`;

    await page.goto('/admin/content/faqs/create', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('nav.fi-topbar');
    await fillText(page, 'question.en', question);
    await submitCreate(page);

    await page.goto('/admin/content/faqs', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('nav.fi-topbar');
    await page.locator('.fi-ta-search-field input').fill(u);
    await page.waitForTimeout(1200);

    await expect(page.getByText(question)).toBeVisible({ timeout: 10000 });

    const row = page.locator('table tbody tr').first();
    await row.locator('.fi-dropdown-trigger button').click();
    await page.locator('.fi-dropdown-list-item:visible', { hasText: 'Delete' }).click();
    await page.getByRole('button', { name: 'Yes, delete' }).click();
    await page.waitForTimeout(1500);

    await expect(page.getByText(question)).not.toBeVisible();
});

test('faq: bulk delete removes every selected record', async ({ page }) => {
    const u = uniqueSuffix();
    const questionA = `E2E bulk-delete-me FAQ A ${u}?`;
    const questionB = `E2E bulk-delete-me FAQ B ${u}?`;

    for (const question of [questionA, questionB]) {
        await page.goto('/admin/content/faqs/create', { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('nav.fi-topbar');
        await fillText(page, 'question.en', question);
        await submitCreate(page);
    }

    await page.goto('/admin/content/faqs', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('nav.fi-topbar');
    // Both rows share the same unique suffix, so searching just that
    // surfaces exactly these two.
    await page.locator('.fi-ta-search-field input').fill(u);
    await page.waitForTimeout(1200);

    const rows = page.locator('table tbody tr');
    await expect(rows).toHaveCount(2, { timeout: 10000 });

    await page.locator('th.fi-ta-selection-cell input[type="checkbox"]').check({ timeout: 10000 });
    await expect(page.getByText('2 records selected')).toBeVisible({ timeout: 10000 });

    await page.getByRole('button', { name: 'Bulk actions', exact: true }).click();
    await page.locator('.fi-dropdown-list-item:visible', { hasText: 'Delete selected' }).click();
    await page.getByRole('button', { name: 'Delete', exact: true }).click();
    await page.waitForTimeout(1500);

    await page.locator('.fi-ta-search-field input').fill(u);
    await page.waitForTimeout(1200);
    await expect(page.getByText(questionA)).not.toBeVisible();
    await expect(page.getByText(questionB)).not.toBeVisible();
});
