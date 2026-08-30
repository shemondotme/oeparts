/**
 * Shared field-interaction helpers for the admin Filament-panel e2e
 * suite. Selectors are taken from live-rendered HTML, not guessed — see
 * crud-create.spec.js's file-level comment for the full writeup of how
 * these were derived (dumping real Create/Edit page HTML).
 */

export function uniqueSuffix() {
    return `${Date.now()}${Math.floor(Math.random() * 1000)}`;
}

/** A safe 2-letter uppercase code, different enough run-to-run to avoid unique-constraint collisions. */
export function uniqueCode2() {
    const a = 65 + (Date.now() % 26);
    const b = 65 + (Math.floor(Date.now() / 7) % 26);
    return String.fromCharCode(a, b);
}

export async function fillText(page, key, value) {
    await page.locator(`[id="form.${key}"]`).fill(value);
}

/**
 * Not every Select in this app uses Filament's custom searchable
 * combobox — confirmed live: Coupon's 2-option discount_type does, but
 * Order's payment_method (also a small fixed enum) renders as a
 * genuinely native `<select id="form.X">`. Whether a given field gets
 * the custom widget is a per-field authoring choice (searchable/multiple/
 * htmlAllowed), not something to assume from field type alone — check
 * for the native element first and fall back to the combobox
 * interaction (click `.fi-select-input-btn`, then click the matching
 * `[data-value]` option inside whichever dropdown panel is currently
 * `:visible` — only one is open at a time, and `data-value` is the
 * field's real submitted value: an enum's backing string, or a
 * relationship's raw foreign-key id).
 */
export async function selectOption(page, key, value) {
    const native = page.locator(`select[id="form.${key}"]`);
    if ((await native.count()) > 0) {
        await native.selectOption(value);
        return;
    }

    const container = page.locator(`[wire\\:partial="schema-component::form.${key}"]`);
    await container.locator('.fi-select-input-btn').click();
    const openListbox = page.locator('.fi-dropdown-panel[role="listbox"]:visible');
    await openListbox.locator(`[data-value="${value}"]`).first().click();
}

/**
 * Selects an option from a large/relationship Select (e.g. Manufacturer)
 * by its visible text instead of a numeric id. These render as searchable,
 * lazy-loaded comboboxes — confirmed live: opening one shows zero options
 * in the DOM until a search term is typed, unlike the small fixed-enum
 * Selects `selectOption()` handles fine. Two things any caller here needs
 * that a naive attempt gets wrong:
 *   1. The search box needs real per-character key events — `.fill()` sets
 *      the value but doesn't reliably trigger the search (confirmed empirically:
 *      it left the results list empty), so this uses `pressSequentially`.
 *   2. Matching by id is fragile in a shared dev database: a specific
 *      row's id can silently stop existing (another suite's delete-flow
 *      test, manual admin cleanup, a reseed) even when the row itself
 *      ("Alfa Romeo", say) is a real, intentionally-stable demo record.
 *      Its name isn't going anywhere; its id might.
 */
export async function selectOptionByText(page, key, searchTerm) {
    const container = page.locator(`[wire\\:partial="schema-component::form.${key}"]`);
    await container.locator('.fi-select-input-btn').click();
    const openListbox = page.locator('.fi-dropdown-panel[role="listbox"]:visible');

    const searchInput = openListbox.locator('input[type="text"], input[type="search"]').first();
    if ((await searchInput.count()) > 0) {
        await searchInput.pressSequentially(searchTerm, { delay: 50 });
    }
    await openListbox.locator('[data-value]', { hasText: searchTerm }).first().click({ timeout: 10000 });
}

/**
 * Submits a Create form. `button[type="submit"]` alone is ambiguous —
 * every admin page also renders a hidden global "Sign out" form whose
 * button is `type="submit"` too — so scope by `wire:target`, which names
 * the specific Livewire method the button triggers ("create" here,
 * "save" on an Edit page) and is unique regardless of anything else on
 * the page.
 */
export async function submitCreate(page) {
    await page.locator('button[type="submit"][wire\\:target="create"]').click();
    await page.waitForURL((url) => !url.pathname.endsWith('/create'), { timeout: 20000 });
}

/**
 * Submits an Edit form's "Save changes" button (`wire:target="save"`,
 * same disambiguation reasoning as submitCreate). A successful save
 * shows a toast notification but does NOT navigate away (Edit pages
 * stay on the same URL) — callers should assert on the resulting UI
 * state (e.g. the field's new value, or the absence of a validation
 * error), not a URL change.
 *
 * Waits for network idle rather than a fixed delay — confirmed live
 * during a frontend/UX audit that a fixed ~1.5s wait is genuinely too
 * short on this dev environment's PHP built-in server often enough to
 * matter: a caller reloading right after can catch the save's own
 * Livewire round-trip still in flight and read back the pre-save value,
 * which looks exactly like "the edit didn't persist" (it did — the
 * assertion just ran before it landed).
 */
export async function submitSave(page) {
    await page.locator('button[type="submit"][wire\\:target="save"]').click();
    await page.waitForLoadState('networkidle', { timeout: 20000 }).catch(() => {});
    await page.waitForTimeout(300);
}
