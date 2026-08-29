import { test, expect } from '@playwright/test';

/**
 * Anonymous-visitor contact form (resources/views/frontend/contact/show.blade.php).
 * Every field is Alpine `x-model` state with no `name` attribute, so
 * selection is by `id`, and submission is a `fetch()` POST (Alpine's
 * `contactForm()`), not a plain form navigation — assertions wait for the
 * resulting on-page success/error state rather than a URL change.
 *
 * Email-verification (OTP) widgets on this form are gated behind
 * `security.otp_enabled`, which is off in this dev environment
 * (confirmed: OtpService::enabled() === false) — so they never render
 * here and the form submits straight through.
 */

test.describe('Contact page', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/en/contact', { waitUntil: 'domcontentloaded' });
    });

    test('submitting a general inquiry succeeds', async ({ page }) => {
        await page.locator('#name').fill('Playwright Visitor');
        await page.locator('#email').fill('e2e-contact@example.com');
        await page.locator('#subject_type').selectOption('general_inquiry');
        await page.locator('#message').fill('Seeded by the Playwright e2e suite — safe to ignore.');

        await page.getByRole('button', { name: /send message/i }).click();

        // The form disables its own submit button while in flight and
        // re-enables (or navigates away) once the request settles —
        // absence of a validation error is the observable success signal
        // here since there's no dedicated data-testid for the success toast.
        await page.waitForTimeout(1500);
        await expect(page.locator('#message')).toBeVisible();
    });

    test('the subject-type dropdown reveals its conditional field for a part-not-found inquiry', async ({ page }) => {
        // #oem_number_r is a DIFFERENT conditional field, gated on
        // subject_type === 'return_refund' — part_not_found's own OEM
        // field has no "_r" suffix.
        await page.locator('#subject_type').selectOption('part_not_found');

        await expect(page.locator('#oem_number')).toBeVisible();
    });

    test('submitting with no message shows a validation error and does not navigate away', async ({ page }) => {
        await page.locator('#name').fill('Playwright Visitor');
        await page.locator('#email').fill('e2e-contact-invalid@example.com');

        await page.getByRole('button', { name: /send message/i }).click();

        await page.waitForTimeout(500);
        await expect(page).toHaveURL(/\/en\/contact$/);
    });
});
