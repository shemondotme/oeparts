import { test, expect } from '@playwright/test';
import { uniqueSuffix } from './helpers.js';

/**
 * MenuItemRelationManager — full create/edit/delete lifecycle. Two things
 * confirmed live that don't match the rest of this suite's conventions:
 *
 * - Filament v4 makes relation managers read-only by default on a
 *   Resource's ViewRecord page (Panel::hasReadOnlyRelationManagersOnResourceViewPagesByDefault()
 *   -> RelationManager::isReadOnly() denies Create/Edit/Delete outright
 *   whenever the owning page extends ViewRecord). This is NOT a bug —
 *   confirmed by tracing Filament's own authorization resolution
 *   (vendor/filament/filament/src/Resources/RelationManagers/RelationManager.php).
 *   Full CRUD only works from the Menu's Edit page, not its View page.
 * - The relation-manager table itself is lazy-loaded (Alpine x-intersect)
 *   and sits below the main Edit form — needs a scroll + wait before any
 *   of its content (or actions) exist in the DOM at all.
 */

test('menu items: create, edit, and delete a menu item end to end', async ({ page }) => {
    const u = uniqueSuffix();
    const label = `E2E Menu Item ${u}`;
    const updatedLabel = `E2E Menu Item ${u} Updated`;

    await page.goto('/admin/content/menus/2/edit', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('nav.fi-topbar');
    await page.mouse.wheel(0, 2000);
    await page.getByRole('button', { name: 'New menu item', exact: true }).waitFor({ timeout: 20000 });

    // Create
    await page.getByRole('button', { name: 'New menu item', exact: true }).click();
    await page.getByRole('heading', { name: 'Create Menu Item' }).waitFor({ timeout: 10000 });
    await page.locator('[id="mountedActionSchema0.label.en"]').fill(label);
    await page.locator('[id="mountedActionSchema0.url"]').fill(`/e2e-menu-item-${u}`);
    await page.getByRole('button', { name: 'Create', exact: true }).click();
    await page.waitForTimeout(1500);

    const row = page.locator('table tbody tr', { hasText: label });
    await expect(row).toBeVisible({ timeout: 10000 });

    // Edit
    await row.getByRole('button', { name: 'Edit', exact: true }).click();
    // Modal heading is "Edit {record title}" — MenuItemRelationManager's
    // recordTitle() resolves to the item's localized label, not a fixed
    // "Menu Item" string.
    await page.getByRole('heading', { name: `Edit ${label}` }).waitFor({ timeout: 10000 });
    await page.locator('[id="mountedActionSchema0.label.en"]').fill(updatedLabel);
    // "Save changes" also matches the owning Edit-Menu form's own submit
    // button outside the modal — scope to the dialog.
    await page.getByRole('dialog').getByRole('button', { name: 'Save changes', exact: true }).click();
    await page.waitForTimeout(1500);

    const updatedRow = page.locator('table tbody tr', { hasText: updatedLabel });
    await expect(updatedRow).toBeVisible({ timeout: 10000 });

    // Delete
    await updatedRow.getByRole('button', { name: 'Delete', exact: true }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Delete', exact: true }).click();
    await page.waitForTimeout(1500);

    await expect(page.locator('table tbody tr', { hasText: updatedLabel })).not.toBeVisible();
});
