import { test, expect } from '@playwright/test';
import { uniqueSuffix } from './helpers.js';

/**
 * Regression test for the same recordTitle bug fixed in
 * MenuItemRelationManager (see that commit / menu-items.spec.js) —
 * ShippingZoneResource\RelationManagers\MethodsRelationManager had the
 * identical crash: ->recordTitleAttribute('name') on ShippingMethod,
 * whose `name` column is translatable/array-cast. Same fix, same shape
 * of test: full create/edit/delete lifecycle from the zone's Edit page
 * (relation managers are read-only on the View page in this Filament
 * version — see menu-items.spec.js's file comment for why).
 */

test('shipping methods: create, edit, and delete a method end to end', async ({ page }) => {
    const u = uniqueSuffix();
    const name = `E2E Method ${u}`;
    const updatedName = `E2E Method ${u} Updated`;

    await page.goto('/admin/shipping-zones/3/edit', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('nav.fi-topbar');
    await page.mouse.wheel(0, 2000);
    // The zone's Edit page tabs Countries/Methods as separate relation
    // managers — Countries is the default active tab.
    await page.getByText('Methods', { exact: true }).click();
    await page.getByRole('button', { name: /new (shipping )?method/i }).waitFor({ timeout: 20000 });

    // Create
    await page.getByRole('button', { name: /new (shipping )?method/i }).click();
    await page.locator('[id="mountedActionSchema0.name.en"]').waitFor({ timeout: 10000 });
    await page.locator('[id="mountedActionSchema0.name.en"]').fill(name);
    await page.locator('[id="mountedActionSchema0.flat_rate"]').fill('9.99');
    await page.locator('[id="mountedActionSchema0.estimated_days_min"]').fill('2');
    await page.locator('[id="mountedActionSchema0.estimated_days_max"]').fill('5');
    await page.getByRole('button', { name: 'Create', exact: true }).click();
    await page.waitForTimeout(1500);

    const row = page.locator('table tbody tr', { hasText: name });
    await expect(row).toBeVisible({ timeout: 10000 });

    // Edit/Delete are grouped behind one "..." ActionGroup trigger here
    // (AdminUi::recordActionsWithoutView()), unlike MenuItemRelationManager's
    // plain array of standalone action buttons.
    await row.locator('.fi-dropdown-trigger button').click();
    await page.locator('.fi-dropdown-list-item:visible', { hasText: 'Edit' }).click();
    await page.locator('[id="mountedActionSchema0.name.en"]').waitFor({ timeout: 10000 });
    await page.locator('[id="mountedActionSchema0.name.en"]').fill(updatedName);
    await page.getByRole('dialog').getByRole('button', { name: 'Save changes', exact: true }).click();
    await page.waitForTimeout(1500);

    const updatedRow = page.locator('table tbody tr', { hasText: updatedName });
    await expect(updatedRow).toBeVisible({ timeout: 10000 });

    // Delete
    await updatedRow.locator('.fi-dropdown-trigger button').click();
    await page.locator('.fi-dropdown-list-item:visible', { hasText: 'Delete' }).click();
    // AdminUi::recordActionsWithoutView()'s DeleteAction customizes the
    // confirm button label to "Yes, delete", not a plain "Delete".
    await page.getByRole('button', { name: 'Yes, delete' }).click();
    await page.waitForTimeout(1500);

    await expect(page.locator('table tbody tr', { hasText: updatedName })).not.toBeVisible();
});
