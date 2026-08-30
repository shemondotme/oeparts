import { test, expect } from '@playwright/test';
import { uniqueSuffix, fillText, submitSave } from './helpers.js';
import { artisanOutput } from '../helpers.js';

/**
 * Real edit-flow tests: load an existing record's Edit page, confirm it
 * actually loaded real data (the field isn't blank), change one
 * identifying field to a fresh value, save, and confirm the new value
 * stuck — both in the form immediately after saving and after a full
 * page reload (proving it round-tripped through the database, not just
 * client-side Livewire state) — then revert the field to what it was.
 *
 * This used to hardcode a specific record id per resource ("real rows
 * this dev DB already has ... queried fresh via tinker right before
 * writing this file"). Confirmed live during a frontend/UX audit that
 * assumption breaks the moment anything else in this shared dev DB
 * deletes a row: CarModel, Category, Language, Manufacturer, BlogPost,
 * Faq, Page, Section, Testimonial and Product all pointed at rows that no
 * longer existed (delete-flows.spec.js's own delete coverage, or manual
 * cleanup of unrelated leaked test data, is enough to do this). Same root
 * cause as crud-create.spec.js's now-fixed Manufacturer select — ids from
 * a shared, actively-mutated database aren't a safe thing to hardcode.
 *
 * ResolveE2eEditTargets resolves the lowest current id per resource at
 * run time instead — always a real row, whatever it happens to be. The
 * revert step at the end of each test (added here, wasn't present before)
 * is what actually makes "whatever real row happens to be there" safe to
 * target at all: the old version permanently renamed whatever it edited to
 * "E2E Edited ..." with no way back, silently vandalizing real seed/demo
 * content on every run (this is very likely how several of the stale-id
 * failures above came to exist as "E2E Edited ..." rows in the first
 * place — a prior run edited a real seed row, and a *later* run's
 * hardcoded id pointed at that now-renamed row, which then eventually got
 * cleaned up as leaked test data, orphaning the id). Reverting means this
 * suite can safely touch a genuine seed row without leaving it altered,
 * regardless of which row it happens to land on.
 */

const EDIT_TARGETS = [
    { name: 'Admin', urlBase: '/admin/admins', field: 'name' },
    { name: 'CarModel', urlBase: '/admin/car-models', field: 'name' },
    { name: 'Carrier', urlBase: '/admin/carriers', field: 'name' },
    { name: 'Category', urlBase: '/admin/content/categories', field: 'name.en' },
    { name: 'Condition', urlBase: '/admin/conditions', field: 'name' },
    { name: 'Coupon', urlBase: '/admin/coupons', field: 'name' },
    { name: 'Customer', urlBase: '/admin/customers', field: 'name' },
    { name: 'IpBlocklist', urlBase: '/admin/ip-blocklists', field: 'reason' },
    { name: 'Language', urlBase: '/admin/languages', field: 'name' },
    { name: 'Manufacturer', urlBase: '/admin/manufacturers', field: 'name.en' },
    { name: 'BlogPost', urlBase: '/admin/content/blog-posts', field: 'title.en' },
    { name: 'Faq', urlBase: '/admin/content/faqs', field: 'question.en' },
    { name: 'Menu', urlBase: '/admin/content/menus', field: 'name' },
    { name: 'Page', urlBase: '/admin/content/pages', field: 'title.en' },
    { name: 'Section', urlBase: '/admin/content/sections', field: 'title.en' },
    { name: 'Testimonial', urlBase: '/admin/content/testimonials', field: 'name' },
    { name: 'NewsletterCampaign', urlBase: '/admin/newsletter-campaigns', field: 'subject' },
    { name: 'Redirect', urlBase: '/admin/redirects', field: 'to_url' },
    { name: 'Role', urlBase: '/admin/roles', field: 'name' },
    { name: 'Product', urlBase: '/admin/products', field: 'name.en' },
    { name: 'ShippingMethod', urlBase: '/admin/shipping-methods', field: 'name.en' },
    { name: 'ShippingZone', urlBase: '/admin/shipping-zones', field: 'name' },
    { name: 'TaxRate', urlBase: '/admin/tax-rates', field: 'country_name' },
    { name: 'Translation', urlBase: '/admin/translations', field: 'value' },
];

const resolvedIds = JSON.parse(artisanOutput('oeparts:e2e:resolve-edit-targets'));

// A resource with zero existing rows (confirmed live: CarModel, in this
// dev DB right now) has nothing to edit — skip it rather than fail on a
// precondition this file can't control, and say so in the test list
// instead of silently vanishing.
const RESOURCES = EDIT_TARGETS
    .map((r) => ({ ...r, id: resolvedIds[r.name], url: `${r.urlBase}/${resolvedIds[r.name]}/edit` }))
    .filter((r) => {
        if (r.id == null) {
            console.warn(`[crud-edit] skipping ${r.name}: no existing rows to edit`);
            return false;
        }
        return true;
    });

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

            // Put it back — this record is whatever real row happened to
            // be lowest-id for this resource, not throwaway fixture data.
            await field.fill(existingValue);
            await submitSave(page);
            await page.reload({ waitUntil: 'domcontentloaded' });
            await page.waitForSelector('nav.fi-topbar');
            await expect(page.locator(`[id="form.${r.field}"]`)).toHaveValue(existingValue);
        });
    }
});
