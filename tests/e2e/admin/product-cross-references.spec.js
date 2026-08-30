import { test, expect } from '@playwright/test';
import { uniqueSuffix } from './helpers.js';
import { artisanOutput } from '../helpers.js';

/**
 * ProductResource's CrossReferencesRelationManager — a simple single-field
 * relation manager (cross_oem_number is a plain string column, unlike the
 * translatable-column bug fixed elsewhere in this session), standalone
 * Edit/Delete buttons (no ActionGroup). Only mutable from the product's
 * Edit page.
 *
 * Product id used to be hardcoded (110) — confirmed live during a
 * frontend/UX audit that id no longer existed in this shared dev DB (same
 * root cause as crud-edit.spec.js's/smoke-sweep.spec.js's own hardcoded-id
 * failures: another test's delete-flow or cleanup pass is enough to shift
 * ids over time). Resolves the lowest currently-existing product id at
 * run time instead.
 */

test('product cross references: create, edit, and delete end to end', async ({ page }) => {
    const productId = JSON.parse(artisanOutput('oeparts:e2e:resolve-edit-targets')).Product;
    const u = uniqueSuffix();
    const oem = `E2E-XREF-${u}`;
    const updatedOem = `E2E-XREF-${u}-UPD`;

    await page.goto(`/admin/products/${productId}/edit`, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('nav.fi-topbar');
    await page.mouse.wheel(0, 3000);
    await page.getByRole('button', { name: /new (product )?cross reference/i }).waitFor({ timeout: 20000 });

    // Create
    await page.getByRole('button', { name: /new (product )?cross reference/i }).click();
    await page.locator('[id="mountedActionSchema0.cross_oem_number"]').waitFor({ timeout: 10000 });
    await page.locator('[id="mountedActionSchema0.cross_oem_number"]').fill(oem);
    await page.getByRole('dialog').getByRole('button', { name: 'Create', exact: true }).click();
    await page.waitForTimeout(1500);

    const row = page.locator('table tbody tr', { hasText: oem });
    await expect(row).toBeVisible({ timeout: 10000 });

    // Edit
    await row.getByRole('button', { name: 'Edit', exact: true }).click();
    await page.locator('[id="mountedActionSchema0.cross_oem_number"]').waitFor({ timeout: 10000 });
    await page.locator('[id="mountedActionSchema0.cross_oem_number"]').fill(updatedOem);
    await page.getByRole('dialog').getByRole('button', { name: 'Save changes', exact: true }).click();
    await page.waitForTimeout(1500);

    const updatedRow = page.locator('table tbody tr', { hasText: updatedOem });
    await expect(updatedRow).toBeVisible({ timeout: 10000 });

    // Delete
    await updatedRow.getByRole('button', { name: 'Delete', exact: true }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Delete', exact: true }).click();
    await page.waitForTimeout(1500);

    await expect(page.locator('table tbody tr', { hasText: updatedOem })).not.toBeVisible();
});
