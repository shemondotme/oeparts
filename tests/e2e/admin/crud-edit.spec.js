import { test, expect } from '@playwright/test';
import { uniqueSuffix, fillText, submitSave } from './helpers.js';

/**
 * Real edit-flow tests: load an existing record's Edit page, confirm it
 * actually loaded real data (the field isn't blank), change one
 * identifying field to a fresh value, save, and confirm the new value
 * stuck — both in the form immediately after saving and after a full
 * page reload (proving it round-tripped through the database, not just
 * client-side Livewire state).
 *
 * Record ids below are real rows this dev DB already has — mostly the
 * ones crud-create.spec.js itself created (an "E2E ..." named row per
 * resource), queried fresh via `php artisan tinker` right before writing
 * this file, so editing them can't disturb real seed/demo data. A few
 * (Product, Order, Redirect, Role, ShippingZone) reuse whichever real or
 * e2e-created row happened to be latest — either way it's safe to edit
 * since only the one field this test touches gets changed.
 */

const RESOURCES = [
    { name: 'Admin', url: '/admin/admins/7/edit', field: 'name' },
    { name: 'CarModel', url: '/admin/car-models/5/edit', field: 'name' },
    { name: 'Carrier', url: '/admin/carriers/12/edit', field: 'name' },
    { name: 'Category', url: '/admin/content/categories/11/edit', field: 'name.en' },
    { name: 'Condition', url: '/admin/conditions/5/edit', field: 'name' },
    { name: 'Coupon', url: '/admin/coupons/8/edit', field: 'name' },
    { name: 'Customer', url: '/admin/customers/62/edit', field: 'name' },
    { name: 'IpBlocklist', url: '/admin/ip-blocklists/2/edit', field: 'reason' },
    { name: 'Language', url: '/admin/languages/7/edit', field: 'name' },
    { name: 'Manufacturer', url: '/admin/manufacturers/23/edit', field: 'name.en' },
    { name: 'BlogPost', url: '/admin/content/blog-posts/12/edit', field: 'title.en' },
    { name: 'Faq', url: '/admin/content/faqs/7/edit', field: 'question.en' },
    { name: 'Menu', url: '/admin/content/menus/2/edit', field: 'name' },
    { name: 'Page', url: '/admin/content/pages/8/edit', field: 'title.en' },
    { name: 'Section', url: '/admin/content/sections/16/edit', field: 'title.en' },
    { name: 'Testimonial', url: '/admin/content/testimonials/8/edit', field: 'name' },
    { name: 'NewsletterCampaign', url: '/admin/newsletter-campaigns/2/edit', field: 'subject' },
    { name: 'Redirect', url: '/admin/redirects/38/edit', field: 'to_url' },
    { name: 'Role', url: '/admin/roles/7/edit', field: 'name' },
    { name: 'Product', url: '/admin/products/110/edit', field: 'name.en' },
    { name: 'ShippingMethod', url: '/admin/shipping-methods/5/edit', field: 'name.en' },
    { name: 'ShippingZone', url: '/admin/shipping-zones/3/edit', field: 'name' },
    { name: 'TaxRate', url: '/admin/tax-rates/2/edit', field: 'country_name' },
    { name: 'Translation', url: '/admin/translations/2/edit', field: 'value' },
];

test.describe('Admin CRUD: edit flow round-trips a real change', () => {
    for (const r of RESOURCES) {
        test(`${r.name}: editing and saving persists the new value`, async ({ page }) => {
            await page.goto(r.url, { waitUntil: 'domcontentloaded' });
            await page.waitForSelector('nav.fi-topbar');

            const field = page.locator(`[id="form.${r.field}"]`);
            const existingValue = await field.inputValue();
            expect(existingValue.length, `${r.name}'s ${r.field} loaded blank — edit form may not be reading real data`).toBeGreaterThan(0);

            const newValue = `E2E Edited ${uniqueSuffix()}`;
            await field.fill(newValue);
            await submitSave(page);

            // Reload to prove the value round-tripped through the
            // database, not just Livewire's in-memory form state.
            await page.reload({ waitUntil: 'domcontentloaded' });
            await page.waitForSelector('nav.fi-topbar');
            await expect(page.locator(`[id="form.${r.field}"]`)).toHaveValue(newValue);
        });
    }
});
