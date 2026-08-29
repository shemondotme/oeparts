import { test, expect } from '@playwright/test';

/**
 * Anonymous-visitor smoke coverage for the parts every page shares: the
 * homepage hero, the sitewide navbar (language switcher, cart link, mobile
 * menu, auth modal trigger), and the footer's register shortcut. Selectors
 * below are taken directly from the live Blade source, not guessed:
 *   resources/views/components/navbar.blade.php
 *   resources/views/components/language-switcher.blade.php
 *   resources/views/components/sections/hero.blade.php
 *   resources/views/components/modals/auth-modal.blade.php
 *   resources/views/components/footer.blade.php
 */

test.describe('Homepage', () => {
    test('loads with the hero OEM search visible', async ({ page }) => {
        await page.goto('/en/', { waitUntil: 'domcontentloaded' });

        await expect(page.locator('#hero-oem-search')).toBeVisible();
    });

    test('submitting the hero search navigates to the OEM search URL', async ({ page }) => {
        await page.goto('/en/', { waitUntil: 'domcontentloaded' });

        // The app normalizes the query (strips separators) before building
        // the URL — a hyphenated query like "SOME-OEM-QUERY" lands on
        // "/en/parts/SOMEOEMQUERY", not the literal typed string. Use a
        // query with no separators so the expected URL is unambiguous.
        const searchInput = page.locator('#hero-oem-search');
        await searchInput.fill('SOMEOEMQUERY');
        await searchInput.press('Enter');

        await page.waitForURL(/\/en\/parts\/SOMEOEMQUERY/, { waitUntil: 'domcontentloaded' });
    });
});

test.describe('Navbar', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/en/', { waitUntil: 'domcontentloaded' });
    });

    test('main nav links resolve without 404s', async ({ page, request }) => {
        // navbar.blade.php builds every href via url()/route() — absolute
        // URLs (http://oeparts.test/...), not root-relative paths.
        const nav = page.locator('nav[aria-label="Main navigation"]');
        const hrefs = await nav.locator('a[href]').evaluateAll((els) =>
            [...new Set(els.map((el) => el.getAttribute('href')))].filter(Boolean)
        );

        expect(hrefs.length).toBeGreaterThan(0);

        for (const href of hrefs) {
            const response = await request.get(href);
            expect(response.status(), href).not.toBe(404);
        }
    });

    test('language switcher opens and switches locale to German', async ({ page }) => {
        const trigger = page.locator('#lang-switcher-trigger');
        await expect(trigger).toBeVisible();
        await trigger.click();

        const menu = page.locator('[role="menu"][aria-labelledby="lang-switcher-trigger"]');
        await expect(menu).toBeVisible();

        // Each item's accessible name is "<Native name> <CODE>" (e.g.
        // "Deutsch DE") — matching on the href is unambiguous, unlike
        // text matching which also has to rule out "Deutsch" containing
        // the substring "de".
        await menu.locator('a[role="menuitem"][href*="/de/"], a[role="menuitem"][href$="/de"]').click();
        // The homepage's own German URL has no trailing slash
        // (http://oeparts.test/de) — match end-of-path or a following
        // slash, not a mandatory trailing one.
        await page.waitForURL(/\/de(\/|$)/, { waitUntil: 'domcontentloaded' });
    });

    test('cart link navigates to the cart page', async ({ page }) => {
        await page.locator('a[href$="/cart"]').first().click();
        await page.waitForURL(/\/en\/cart$/, { waitUntil: 'domcontentloaded' });
    });

    test('mobile menu toggle opens and closes the mobile nav panel', async ({ page }) => {
        await page.setViewportSize({ width: 375, height: 800 });
        await page.reload({ waitUntil: 'domcontentloaded' });

        const toggle = page.locator('button[aria-label="Toggle navigation menu"]');
        await expect(toggle).toBeVisible();
        await expect(toggle).toHaveAttribute('aria-expanded', 'false');

        await toggle.click();
        await expect(toggle).toHaveAttribute('aria-expanded', 'true');
        await expect(page.locator('#mobile-menu')).toBeVisible();

        await toggle.click();
        await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    });

    test('Sign in opens the auth modal on the login tab', async ({ page }) => {
        await page.getByRole('button', { name: 'Sign in' }).first().click();

        const modal = page.locator('[x-data="authModal()"]');
        await expect(modal.locator('#auth-panel-login')).toBeVisible();
        await expect(modal.locator('#login-email')).toBeVisible();
    });

    test('Escape closes the auth modal', async ({ page }) => {
        await page.getByRole('button', { name: 'Sign in' }).first().click();
        await expect(page.locator('#login-email')).toBeVisible();

        await page.keyboard.press('Escape');
        await expect(page.locator('#login-email')).toBeHidden();
    });

    test('switching to the Register tab shows the registration form', async ({ page }) => {
        await page.getByRole('button', { name: 'Sign in' }).first().click();
        await page.locator('#auth-tab-register').click();

        await expect(page.locator('#reg-email')).toBeVisible();
    });
});

test.describe('Footer', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/en/', { waitUntil: 'domcontentloaded' });
    });

    test('the register shortcut opens the auth modal directly on the Register tab', async ({ page }) => {
        // footer.blade.php is the one confirmed call site that passes
        // {tab:'register'} to open-auth-modal — every other trigger
        // defaults to the login tab.
        await page.locator('footer').getByText(/register/i).first().click();

        await expect(page.locator('#reg-email')).toBeVisible();
    });

    test('newsletter subscribe form accepts a valid email', async ({ page }) => {
        const emailInput = page.locator('#newsletter-email');

        // The newsletter section is an optional, admin-configured homepage
        // block — only assert behavior when it's actually present rather
        // than failing the whole suite on installs that don't enable it.
        if ((await emailInput.count()) === 0) {
            test.skip();
        }

        // Below-the-fold sections render a skeleton first and only reveal
        // their real content once an IntersectionObserver on the section's
        // (always-in-layout) wrapper fires (home.blade.php's lazy-section
        // block) — the real #newsletter-email input is display:none via
        // x-show="loaded" until then, so it has no box for
        // scrollIntoViewIfNeeded() to target. Scroll the page itself
        // instead, which moves the still-visible skeleton wrapper through
        // the viewport and triggers the observer.
        await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
        await expect(emailInput).toBeVisible({ timeout: 10000 });

        await emailInput.fill(`e2e-newsletter-${Date.now()}@example.com`);
        await emailInput.locator('xpath=ancestor::form[1]').getByRole('button', { name: /subscribe/i }).click();

        // A successful subscribe must never leave the error region populated.
        await page.waitForTimeout(500);
        await expect(page.locator('#newsletter-error')).toBeEmpty();
    });
});
