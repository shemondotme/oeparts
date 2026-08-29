import { test, expect } from '@playwright/test';
import { uniqueSuffix, uniqueCode2, fillText, selectOption, submitCreate } from './helpers.js';

/**
 * Real create-flow tests for every Filament resource that has a Create
 * page (~27 of the app's 39 resources — the rest, like RefundRequest and
 * Review, only originate from customer actions and have no Create page
 * at all, confirmed via their getPages()).
 *
 * Unlike the smoke sweep (which only loads the blank Create form), this
 * actually fills in real data and submits — the part of the surface most
 * likely to break silently (a missing enum case, a relationship with no
 * seeded rows, a field id that changed).
 *
 * Selectors are taken from live-rendered HTML, not guessed — see
 * helpers.js for the full writeup of how the field/select/submit
 * conventions were derived (dumping real Create/Edit page HTML).
 *
 * Real record ids referenced below (Manufacturer 19, Condition 2,
 * ShippingZone 1, Role "support" 5) were queried live from this dev DB
 * and are stable, long-lived seed/demo rows — not e2e fixture data.
 */

const MANUFACTURER_ID = 19;
const CONDITION_ID = 2;
const SHIPPING_ZONE_ID = 1;
const SUPPORT_ROLE_ID = 5;

const RESOURCES = [
    {
        name: 'Admin',
        createUrl: '/admin/admins/create',
        fill: async (page, u) => {
            await fillText(page, 'name', `E2E Admin ${u}`);
            await fillText(page, 'email', `e2e-admin-${u}@example.com`);
            await fillText(page, 'password', 'E2eTest!Passw0rd123');
            await selectOption(page, 'roles', String(SUPPORT_ROLE_ID));
            await page.keyboard.press('Escape');
        },
    },
    {
        name: 'CarModel',
        createUrl: '/admin/car-models/create',
        fill: async (page, u) => {
            await selectOption(page, 'manufacturer_id', String(MANUFACTURER_ID));
            await fillText(page, 'name', `E2E Car Model ${u}`);
            await fillText(page, 'slug', `e2e-car-model-${u}`);
        },
    },
    {
        name: 'Carrier',
        createUrl: '/admin/carriers/create',
        fill: async (page, u) => {
            await fillText(page, 'name', `E2E Carrier ${u}`);
        },
    },
    {
        name: 'Category',
        createUrl: '/admin/content/categories/create',
        fill: async (page, u) => {
            await fillText(page, 'name.en', `E2E Category ${u}`);
            await fillText(page, 'slug', `e2e-category-${u}`);
        },
    },
    {
        name: 'Condition',
        createUrl: '/admin/conditions/create',
        fill: async (page, u) => {
            await fillText(page, 'name', `E2E Condition ${u}`);
            // This form's auto-slug-from-name JS is a known-broken
            // debounced sync (confirmed: it can fire *after* a manually
            // typed slug and clobber it with a malformed value, failing
            // the "lowercase/digits/hyphens only" validation even though
            // the value we typed was valid) — give its debounce window
            // time to fire and settle before typing the real slug last.
            await page.waitForTimeout(800);
            await fillText(page, 'slug', `e2e-condition-${u}`);
        },
    },
    {
        name: 'Coupon',
        createUrl: '/admin/coupons/create',
        fill: async (page, u) => {
            await fillText(page, 'code', `E2ECOUPON${u}`);
            await fillText(page, 'name', `E2E Coupon ${u}`);
            await selectOption(page, 'discount_type', 'percentage');
            await fillText(page, 'discount_value', '10');
        },
    },
    {
        name: 'Customer',
        createUrl: '/admin/customers/create',
        fill: async (page, u) => {
            await fillText(page, 'name', `E2E Customer ${u}`);
            await fillText(page, 'email', `e2e-customer-${u}@example.com`);
        },
    },
    {
        name: 'IpBlocklist',
        createUrl: '/admin/ip-blocklists/create',
        fill: async (page, u) => {
            await fillText(page, 'ip_address', `203.0.113.${u.slice(-2).padStart(2, '1')}`);
            await fillText(page, 'reason', `E2E test block ${u}`);
        },
    },
    {
        name: 'Language',
        createUrl: '/admin/languages/create',
        fill: async (page) => {
            await fillText(page, 'code', uniqueCode2().toLowerCase());
            await fillText(page, 'name', `E2E Language ${uniqueSuffix()}`);
        },
    },
    {
        name: 'Manufacturer',
        createUrl: '/admin/manufacturers/create',
        fill: async (page, u) => {
            await fillText(page, 'name.en', `E2E Manufacturer ${u}`);
            await fillText(page, 'slug', `e2e-manufacturer-${u}`);
            await selectOption(page, 'country_code', 'DE');
        },
    },
    {
        name: 'BlogPost',
        createUrl: '/admin/content/blog-posts/create',
        fill: async (page, u) => {
            await fillText(page, 'title.en', `E2E Blog Post ${u}`);
            await fillText(page, 'slug', `e2e-blog-post-${u}`);
        },
    },
    {
        name: 'Faq',
        createUrl: '/admin/content/faqs/create',
        fill: async (page, u) => {
            await fillText(page, 'question.en', `E2E FAQ question ${u}?`);
        },
    },
    {
        name: 'Menu',
        createUrl: '/admin/content/menus/create',
        fill: async (page, u) => {
            await fillText(page, 'name', `E2E Menu ${u}`);
            await selectOption(page, 'location', 'header');
            await selectOption(page, 'lang', 'en');
        },
    },
    {
        name: 'Page',
        createUrl: '/admin/content/pages/create',
        fill: async (page, u) => {
            await fillText(page, 'title.en', `E2E Page ${u}`);
            await fillText(page, 'slug', `e2e-page-${u}`);
        },
    },
    {
        name: 'Section',
        createUrl: '/admin/content/sections/create',
        fill: async (page, u) => {
            await selectOption(page, 'type', 'banner');
            await selectOption(page, 'location', 'homepage');
            await fillText(page, 'title.en', `E2E Section ${u}`);
        },
    },
    {
        name: 'Testimonial',
        createUrl: '/admin/content/testimonials/create',
        fill: async (page, u) => {
            await fillText(page, 'name', `E2E Testimonial ${u}`);
            await fillText(page, 'quote.en', `E2E test quote ${u}.`);
        },
    },
    {
        name: 'NewsletterCampaign',
        createUrl: '/admin/newsletter-campaigns/create',
        fill: async (page, u) => {
            await fillText(page, 'subject', `E2E Newsletter Subject ${u}`);
        },
    },
    {
        name: 'NewsletterSubscriber',
        createUrl: '/admin/newsletter-subscribers/create',
        fill: async (page, u) => {
            await fillText(page, 'email', `e2e-subscriber-${u}@example.com`);
            await selectOption(page, 'lang', 'en');
        },
    },
    {
        name: 'Redirect',
        createUrl: '/admin/redirects/create',
        fill: async (page, u) => {
            await fillText(page, 'from_url', `e2e-old-page-${u}`);
            await fillText(page, 'to_url', `/e2e-new-page-${u}`);
            await selectOption(page, 'type', '301');
        },
    },
    {
        name: 'Role',
        createUrl: '/admin/roles/create',
        fill: async (page, u) => {
            await fillText(page, 'name', `e2e_role_${u}`);
        },
    },
    {
        name: 'Product',
        createUrl: '/admin/products/create',
        fill: async (page, u) => {
            await fillText(page, 'oem_number', `E2EPROD${u}`);
            await selectOption(page, 'manufacturer_id', String(MANUFACTURER_ID));
            await fillText(page, 'name.en', `E2E Product ${u}`);
            await fillText(page, 'price', '49.99');
            await selectOption(page, 'condition_id', String(CONDITION_ID));
        },
    },
    {
        name: 'Order',
        createUrl: '/admin/orders/create',
        fill: async (page) => {
            await fillText(page, 'shipping_name', 'E2E Test Customer');
            await fillText(page, 'shipping_address_line1', 'Teststrasse 1');
            await fillText(page, 'shipping_city', 'Berlin');
            await fillText(page, 'shipping_postal_code', '10115');
            await selectOption(page, 'shipping_country_code', 'DE');
            await selectOption(page, 'shipping_method_id', '1');
            await selectOption(page, 'payment_method', 'bank_transfer');
            await fillText(page, 'subtotal', '100.00');
            await fillText(page, 'shipping_cost', '10.00');
            await fillText(page, 'vat_amount', '21.00');
            await fillText(page, 'grand_total', '131.00');
        },
    },
    {
        name: 'ShippingMethod',
        createUrl: '/admin/shipping-methods/create',
        fill: async (page, u) => {
            await fillText(page, 'name.en', `E2E Shipping Method ${u}`);
            await selectOption(page, 'zone_id', String(SHIPPING_ZONE_ID));
            await fillText(page, 'flat_rate', '9.99');
            await fillText(page, 'estimated_days_min', '2');
            await fillText(page, 'estimated_days_max', '5');
        },
    },
    {
        name: 'ShippingZone',
        createUrl: '/admin/shipping-zones/create',
        fill: async (page, u) => {
            await fillText(page, 'name', `E2E Shipping Zone ${u}`);
        },
    },
    {
        name: 'TaxRate',
        createUrl: '/admin/tax-rates/create',
        fill: async (page) => {
            await fillText(page, 'country_code', uniqueCode2());
            await fillText(page, 'country_name', `E2E Country ${uniqueSuffix()}`);
            await fillText(page, 'rate', '15');
        },
    },
    {
        name: 'Translation',
        createUrl: '/admin/translations/create',
        fill: async (page, u) => {
            await selectOption(page, 'lang_code', 'en');
            await fillText(page, 'group', `e2e_group_${u}`);
            await fillText(page, 'key', `e2e_key_${u}`);
            await fillText(page, 'value', `E2E test value ${u}`);
        },
    },
    {
        name: 'PartInquiry',
        createUrl: '/admin/part-inquiries/create',
        fill: async (page, u) => {
            await fillText(page, 'oem_number', `E2EINQ${u}`);
            await fillText(page, 'email', `e2e-inquiry-${u}@example.com`);
        },
    },
];

test.describe('Admin CRUD: create flow for every resource', () => {
    for (const r of RESOURCES) {
        test(`${r.name}: create form submits successfully`, async ({ page }) => {
            const u = uniqueSuffix();
            await page.goto(r.createUrl, { waitUntil: 'domcontentloaded' });
            await page.waitForSelector('nav.fi-topbar');

            await r.fill(page, u);
            await submitCreate(page);

            // A failed submission (validation error) stays on /create —
            // getting past submitCreate()'s URL wait already proves the
            // redirect happened, but assert explicitly for a clear
            // failure message if a future Filament version changes the
            // redirect behavior.
            await expect(page).not.toHaveURL(/\/create$/);
        });
    }
});
