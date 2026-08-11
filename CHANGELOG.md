# Changelog — OeParts

All notable changes to this project are documented here.

## 1.0.18 — 2026-08-11

### Fixed
- **Hardened the update process against overlapping progress polls.** A poll to the update-progress endpoint slower than the browser's polling interval (more likely on a larger database) could overlap with the next poll, both running the same step at once — surfaced live as a `No query results for model [App\Models\BackupRun]` error during the pre-update backup step. Each step is now locked so an overlapping poll is safely skipped instead of re-run, and a backup run whose record genuinely goes missing mid-run now reports a clear message instead of a raw database error.

## 1.0.17 — 2026-08-11

### Fixed
- **Hotfix for v1.0.16**: the update process could fail during the migration step with `Duplicate key name products_normalized_oem_ngram_fulltext`, stopping the update from completing. Caused by the new full-text search index migration not being safe to retry from a partially-applied state (MySQL's `ALTER TABLE` isn't transactional, so an interrupted migration run can leave the index in place without Laravel recording it as done). The migration now checks whether the index already exists before creating it. If you hit this on v1.0.16, updating again will now succeed; a v1.0.15 install can update straight to this version.

## 1.0.16 — 2026-08-10

A large batch: a full audit of the Auth, Catalog, Marketing, CMS, Support, and System modules (89 findings, all fixed), plus a dedicated performance pass after the catalog grew to ~100k products. Nothing manual required — every migration applies automatically.

### Security
- **Session fixation on login and registration** — a session ID planted in a victim's browser before they logged in (shared terminal, subdomain cookie trick, leaked session ID) previously inherited full account access the moment they authenticated. Both password and social login now regenerate the session ID on login.
- **Hardened social login (Google/Facebook)** — fixed a routing bug that swapped the `lang`/`provider` parameters and made social login entirely non-functional; existing accounts are now only matched by email when the provider itself verified that address; deactivated accounts and the "registration disabled" setting are now enforced on the social path exactly as they already were on password signup.
- **Prevented a non-super_admin admin from granting themselves (or anyone) the super_admin role** — the Admins → Roles picker listed every role including super_admin to anyone holding the generic "edit admins" permission, an unintended full-access escalation path.
- **Admin/customer password hashes and remember-me tokens were being written into the Activity Log in plain text** on every account create/update/delete — visible to any admin with "view activity logs", not just super_admin. Both are now redacted, matching the `***` convention already used for encrypted settings.
- **Fixed an XSS gap in the WYSIWYG editor's preview endpoint** — its sanitizer stripped disallowed tag names but left attributes on allowed tags untouched, so something like `<img onerror=...>` passed straight through. Now uses the project's existing HTMLPurifier profile.
- **Account self-deletion (Account → Settings) now requires re-entering your current password** — previously only a client-side confirmation dialog stood between a hijacked/left-open session and permanently deleting the account.
- **Password-reset now gives an identical response for an unregistered email as for a real one** — the two previously took visibly different UI paths, an account-enumeration signal independent of the message wording.
- **Login throttling is now keyed by email+IP everywhere**, not IP alone in one spot — an attacker spraying bad credentials from one IP could otherwise lock out every legitimate user sharing that IP (office NAT/CGNAT).
- Closed a missing-authorization gap on the CMS section live-preview endpoint (low impact — read-only, auto-escaped output — but inconsistent with its sibling endpoints).

### Fixed — Catalog & Search
- **Product stock now automatically restores when an order is cancelled or refunded** — previously it flipped to "out of stock" the instant an order was placed and nothing ever flipped it back, permanently misrepresenting availability for anything that was never actually delivered.
- **Checkout now re-verifies stock at order creation, not just at add-to-cart** — closes a window where a product going out of stock (or a second concurrent checkout for the last unit) between add-to-cart and payment still resulted in a charged, unfulfillable order.
- Product's soft-delete now has a real restore path in the admin (previously a deleted product, and its order/cart history, had no way back for anyone).
- The Condition bulk-delete action no longer fatal-errors and partially deletes when one of the selected conditions is still in use — now all-or-nothing.
- Deleting a single Condition still in use by products now shows a friendly warning instead of a raw database error.
- The XML product sitemap no longer writes a duplicate URL per seller/condition sharing the same OEM, and no longer excludes in-stock-filtered (but still visible) products.
- The XML car-model sitemap no longer lists models under a deactivated manufacturer (those pages 404 on crawl).
- Non-English visitors now see the Brands page's A–Z letter index and grouping built from the correct language, not always English.
- The catalog search box's free-text `q` search now matches the requested language's product name, not the raw multi-language JSON blob.
- Search/autocomplete/failed-search logs now correctly record their manufacturer/car-model context (columns existed in code but not in the database) and always get a real timestamp (previously silently `NULL`, breaking the zero-results "you might mean" suggestions, the admin Search Logs sort, and the failed-searches nav badge).
- Search result caches now invalidate correctly whenever a product's price, stock, or active state changes.
- Removed a long-dead, always-empty public API endpoint (`/api/v1/parts/{oem}/supersessions`) referencing a data model that was never built.

### Fixed — CMS
- **Page/BlogPost creation and editing was crashing on every single save** in the admin panel (a missing default on the SEO "robots" field) — found and fixed during this release's own final test sweep.
- **The Menu Builder now actually renders in the storefront header and footer** — a fully-built admin feature with no code reading it until now; opt-in, existing sites keep their current nav until an operator builds a menu.
- **The Translations admin screen now actually changes site copy** — edits previously had zero effect on any page; now layered over the file-based translations, database wins when both exist.
- **A Page's "Set as Homepage" / "Show in Header" / "Show in Footer" toggles now actually do what their own helper text has always promised.**
- **SEO Meta (canonical URL, Open Graph, robots directives) is now wired into Page and BlogPost edit screens and their real rendered output** — previously an orphaned feature with no way to create or use it.
- **Deactivating/activating a Language now actually takes effect sitewide** — six different places (locale routing, sitemaps, both language switchers, account settings) each independently hardcoded the same five-language list.
- Matched redirects no longer crash with a 500 (an enum was passed somewhere an integer status code was required); the "no redirect configured" cache-miss case is now actually cached, removing a database query from every storefront page view.
- Redirect URLs are now normalized consistently, duplicate/looping redirects are rejected with a friendly error instead of a crash, and creating a new redirect no longer crashes on its Hit Count field.
- Deleting a media file still referenced by a Page/Post/Manufacturer/SEO record now shows a friendly "still in use" error instead of silently breaking that reference.
- Fixed a crash creating a menu item that links to a CMS page rather than a raw URL.
- A duplicate Language code now shows a friendly inline validation error instead of a raw database error.

### Fixed — Checkout, Support & Marketing
- **Guest abandoned-cart recovery emails now actually go out** — a guest's email was never persisted anywhere the recovery job could find it; only logged-in customers' abandoned carts were ever recovered before.
- Newsletter campaign sends are now safely retry-safe — a mid-send failure no longer risks double-emailing subscribers who already received that campaign.
- A newsletter campaign can no longer be sent twice from a backed-up queue or a double-clicked "Send Now".
- Duplicating an already-sent newsletter campaign no longer inherits its old (already-past) schedule and silently re-sends itself.
- Newsletter unsubscribe/confirm links no longer trigger just from an email client's link-prescanning — they now require an actual page visit and a real button click.
- A manually admin-added newsletter subscriber can now actually receive campaigns (was crashing at send time).
- Scheduled-future blog posts no longer appear early on the homepage or in category/tag counts.
- The Part Inquiry "New Inquiry" admin page (for phone/in-person requests) can now actually be filled in — every field was locked regardless of context.
- A new Part Inquiry now raises an admin bell notification, not just an easy-to-miss email.
- Refund status can no longer be changed by directly editing the record, bypassing the approve/reject/mark-processed workflow and its side effects; concurrent double-processing of the same refund is now blocked.
- Global search results for Contact Messages are no longer all indistinguishable from each other.
- Downloading an invoice PDF now actually serves the pre-generated cached copy instead of silently re-rendering it live on every request.

### Fixed — System & Admin
- **"Seed Demo Data" in the admin panel could silently wipe the entire database** — a flag-collision bug meant its normal confirmation click also triggered a full `migrate:fresh`. Now requires its own explicit flag, plus an additional production safeguard.
- Cron/scheduled task history (Admin → System → Scheduled Tasks) is now actually recorded — the page and its "failed today" badge were permanently empty before.
- The "Run Now" button on a scheduled task now actually runs it, instead of failing every time.
- Moved the audit-log retention setting out of Search Settings (where it was mislabeled) into Security Settings, since it also controls how far back login/admin-action history can be investigated.
- Closed two race conditions where two near-simultaneous requests could both pass a "one at a time" guard (product catalog import start, and the HTTP cron-fallback trigger).
- Sensitive install-time credentials (mail password, admin password hash) are no longer left sitting in a state file indefinitely after a successful install.
- VIES VAT-number validation now has a real timeout on slow/unresponsive responses, not just on the initial connection.
- Re-validating the same VAT number within its 24-hour cache window no longer burns through the rate limit for no reason.
- Settings are now read from cache on every request instead of running four uncached queries on every single page load.
- A shipped order can no longer be wrongly auto-completed off a stale "shipped" record from months earlier if it was later moved back to Processing and re-shipped.
- OTP resend requests can no longer create a duplicate, guessable one-time code under rapid double-clicking.
- Fixed several smaller inconsistencies: rejecting a refund now stamps a processed timestamp; the password-change form now respects the configured minimum length everywhere; expired-OTP cleanup no longer deletes a code still inside its "recently verified" grace window; a Testimonial rating outside 1–5 is now rejected at the database level, not just in the admin form.

### Performance — built for the ~100k product catalog
- **Bulk Update Products** now processes matching products in chunks instead of loading the entire matched set into memory at once — removes a real memory/timeout risk on a broad filter against a large catalog.
- Added missing database indexes backing the admin product list's default sort and the out-of-stock navigation badge's query.
- **OEM partial/substring search** (storefront fallback, admin table search, admin global search) no longer forces a full table scan on every keystroke — now backed by a MySQL full-text index. Also fixed a pagination bug this surfaced: search results with many identically-priced/stocked products (common right after a bulk import) could shift between pages on reload.
- **"Regenerate sitemap now"** no longer risks running past the request timeout on a large catalog — it now runs as a background job.
- **The storefront OEM search box's autocomplete dropdown is now actually wired up** — the caching and API endpoint behind it already existed but nothing ever called it; it now suggests matches live as you type, with keyboard navigation.
- Guest visitors now get a short-lived, browser-only cache header on safe pages (home, search, brand, blog, CMS pages) — a repeat view or back/forward navigation can skip a full round-trip to the server.

## 1.0.15 — 2026-08-09

### Added
- **Paysera added as a second card payment gateway alongside Airwallex** — wired in the same way as Airwallex (raw HTTP, no SDK): OAuth2 client-credentials auth, order + payment-link creation, and a signature-verified webhook. Card (Airwallex) and Paysera are offered side by side at checkout as independent options.
- **"Hold Funds Until Shipment" for Airwallex card payments** (opt-in, off by default — existing behavior is unchanged unless enabled) — authorizes and holds the customer's card at checkout instead of charging immediately; the charge only actually happens when the order ships (or an admin captures it early from the order page), so you're never left charging a customer for an order that turns out unfulfillable.
- **Git-managed installs now cryptographically verify the exact code `git checkout` pulls down**, not just a downloaded zip's signature — closes a gap where a compromised or re-pushed git remote could have served different code under a validly-signed release tag without detection.

### Security
- **Fixed a path-traversal vulnerability in cross-server backup restore** — a restore manifest's file path was joined onto the restore destination without sanitization, which could allow a crafted backup manifest to write outside the intended restore directory.
- **The legacy `backup:database` command exposed your database password to other local users on shared hosting** — it was passed to `mysqldump` as a plain command-line argument, visible to anyone on the same server via `ps aux` or `/proc/<pid>/cmdline`. Now passed through a temporary, permission-locked credentials file. If you've run this specific command on a shared host, consider rotating that database password.

### Fixed
- **The `/api/cart/*` endpoints used by the mobile app were completely broken** — every single request (add to cart, update quantity, apply coupon, etc.) failed outright due to a routing mismatch, plus a wrong method name and wrong service calls on top. All cart API endpoints now work correctly, with new regression tests covering them.
- **A double-click (or slow-network retry) on "Place Order" could create two paid orders from the same cart** — checkout now locks the cart during order creation so a second concurrent submission cleanly fails instead of double-charging.
- **The payment-success and payment-return pages could fail to recognize a payment that had actually completed successfully**, due to a status comparison bug — affected both Airwallex and Paysera checkouts.
- **Invoice PDFs from different orders placed in the same month could silently overwrite one another** — the save path wasn't unique per order. Every invoice is now saved under its own order number.
- **Invoice PDFs were printing customer names in all-lowercase** ("John Doe" rendered as "john doe" on the billing document).
- **The mobile app's checkout could accept a shipping method that doesn't actually serve the customer's destination country**, and the mobile shipping-methods endpoint had no way to filter by country at all — both fixed; requesting an out-of-zone method is now rejected server-side, and `/api/shipping-methods?country_code=..` now filters correctly.
- **VAT number validation could wrongly reject a valid EU VAT number** when its own embedded country prefix (e.g. the "DE" in "DE123456789") disagreed with a separately-selected country field — the VAT number's own prefix now always takes priority.
- **A coupon configured as "0 = unlimited" uses could incorrectly block every customer from using it (per-customer limit) or reject its own use at checkout (total limit) despite having just been accepted as valid** — both limits now correctly treat 0 the same as no limit at all, matching what the admin panel's own help text has always said.
- **A single-use coupon could, under concurrent checkouts, be redeemed more than once** — usage tracking now locks correctly so this can't happen.
- **A coupon's discount amount could go stale if the cart changed after the coupon was applied** (adding/removing items after applying a % coupon), leading to an incorrect final discount on the order. The discount is now recalculated fresh against the final cart, immediately before the order is placed.
- **Abandoned-cart recovery emails could be marked "sent" even when the email actually failed to deliver**, which meant that customer would never get a retry. The record is now only marked sent once delivery genuinely succeeds.
- **A backup could get permanently stuck "running"** after a database error mid-run, silently blocking every future backup and update until manually cleared from the admin panel.
- **The backup restore command showed version-compatibility warnings *after* the destructive confirmation prompt** — you could confirm an overwrite before ever seeing the warning most relevant to that decision. Warnings (and version-check failures) now surface before you're asked to confirm. The documented `--strict-version` safety flag is also now actually usable — it existed in the underlying options but was never exposed on the command line.
- **A failed update rollback could report itself as successful**, hiding a broken update from the Recovery Console at the exact moment its real recovery steps were needed most.
- **A git-managed install with a missing/unreadable `version.json` only warned instead of blocking an update** — since the rollback path for a git install depends on knowing the current version, this risked starting an update with no way back if anything went wrong. Now hard-blocked for git installs specifically (the zip-update path doesn't share this dependency, so it still only warns there).
- **Backup retention (GFS pruning) could evict a different backup type's files sharing the same day's slot** — retention now correctly scopes to each backup profile independently.
- **`storage/logs/install-*.log` and `updates-*.log` grew forever with no cleanup**, unlike every other log type in the app. Now pruned daily in the same sweep as the rest, honoring the existing 30-day retention setting.
- **Paysera payments were sometimes internally mislabeled as "Bank Transfer"**, showing bank-transfer instructions on the payment page for an order that was never a bank transfer. A retried/duplicate Paysera webhook could also fail unnecessarily, and the webhook duplicate-detection window was about 60x shorter than configured (a units bug: minutes vs. seconds). Manual-capture orders could also receive two confirmation emails instead of one.

## 1.0.14 — 2026-07-31

### Added
- **The one-click updater now works for git-managed deployments** — previously it hard-refused to run at all ("update with git, not the one-click updater"), forcing a manual SSH update every release. A new git-based apply path (fetch + checkout the release tag, `composer install`) runs through the exact same backup-first, poll-driven, auto-rollback flow as the zip-based path — Apply Update now does everything for a git deployment too, no manual commands.
- **RolesSeeder/SettingsSeeder/TaxRatesSeeder/CmsFooterPagesSeeder now run automatically on every update** (rewritten to be purely additive — never revoke a permission or overwrite a value an admin already customized), so this release's new permissions, settings, tax rates, and footer pages backfill themselves with no manual `db:seed` commands.
- **Bulk Update Products rebuilt** (Catalog → Bulk Update Products) — filter products by manufacturer, condition, car model, price range, OEM number, or date added, then apply a price change (% or fixed), condition change, active/inactive toggle, delivery time, or MOQ across every match, not just a checkbox-selected page of rows. Every apply is previewable, requires an explicit confirmation, warns on batches over 500 products, can be downloaded as CSV before committing, and is fully revertible with one click from the Bulk Update Log — split across three new permissions (`bulk update product prices/stock/details`) so roles can be scoped per action type. Large batches email `super_admin`s automatically.
- **Country-based VAT** (opt-in, off by default) — Tax Settings gained a toggle and a new Tax Rates page (Commerce) for per-country VAT rates; when enabled, checkout charges the rate of the customer's shipping country instead of one flat rate for everyone, falling back to the flat rate for any country with no active rate configured. Seeded with starting rates for all 32 EU/EEA countries (including the UK) — **verify every rate before enabling**, these are a documented starting point, not a live feed.
- **Missing `returns-policy` and `shipping-information` footer pages** — the storefront footer has linked to these since before this release, but neither page existed in the database (a seeder/footer drift), so both 404'd. Both now ship with real (editable) content in all 5 languages.
- **"Add All EU/EEA Countries" button** on a Shipping Zone's Countries tab — adds the standard 32-country list in one click, skipping any already assigned; doesn't touch existing countries or other zones.
- **"Back to list" button on every resource's Create/Edit/View page** — previously the only way back to a list was the small breadcrumb link at the top.
- **Google/Facebook OAuth credentials moved off `.env`** — set under Settings → Authentication Settings → Social Login (OAuth), stored encrypted in the database, same pattern as Airwallex payment credentials.
- **Nginx deployment support** — `deploy/nginx/oeparts.conf` (previously only Apache's `public/.htaccess` had any web-server config in the repo, despite the README listing Nginx as supported) and `deploy/php-fpm/oeparts-pool-overrides.conf` for upload/execution limits (`.htaccess`'s overrides silently do nothing under PHP-FPM, which is what Nginx and increasingly Apache both actually run). A root-level `.htaccess` now also blocks direct access if a host's document root is ever misconfigured to the project root instead of `public/`.
- **`TRUSTED_PROXIES`** — new opt-in `.env` key for deployments where Nginx/Apache sits in front of PHP-FPM as a reverse proxy, or behind a CDN/load balancer; without it, IP-based blocking and generated `https://` URLs see the proxy's IP/scheme instead of the real client's. Off (no behavior change) unless set.

### Fixed
- **Selecting a Manufacturer filter on Products, Car Models, Categories, or Blog Posts threw a 500** — Filament's default filter-indicator-chip logic string-interpolates the filtered relation's raw attribute, which is a translatable JSON array (not a string) for `manufacturer.name`/`category.name`; the resulting PHP warning is fatal under this app's error-reporting config. Worse, because the filter is session-persisted, the *next* page load 500'd too, with no way back except clearing cookies. All four filters (plus SeoMeta's incomplete `robots` filter dropdown) now render correctly.
- **The Bulk Update Log page 500'd the moment it had any rows** — same root cause as above (a different case of it): `action_type` is cast to a PHP enum, but the page's badge-formatting code compared the raw enum instance against string literals, which never matches, falling through to `ucfirst()` on an object. Existing test coverage never caught this because it only ever exercised the page with an empty table.
- **Fresh installs never ran `storage:link`** — only the update system's post-update steps did, so uploaded product/blog images 404'd on any brand-new install until someone manually ran the command.
- **Footer logo icon was hidden entirely on mobile** (`hidden sm:block`) — unlike the navbar, which always shows its icon — now shown at every screen size, matching the navbar.
- **`.env` cleanup**: removed `AIRWALLEX_*` (never read from `.env` — `PaymentService` only reads Settings → Payment Settings) and `VIES_TIMEOUT` (never referenced anywhere in the codebase).
- **The "Europe" shipping zone had zero countries after seeding** — `ShippingZonesAndMethodsSeeder`'s own docblock claimed a 6-zone pan-European matrix; the actual code created one zone and three methods but never created a single `ShippingCountry` row. Checkout still worked (a missing zone match silently falls back to "every active method"), but zone-based shipping rules had no effect at all. Fresh installs now seed all 32 countries into the zone; existing installs should use the new "Add All EU/EEA Countries" button instead of re-running the seeder (which truncates first).
- **Every zip-based update left the previous release's code sitting in `storage/app/updates/swap-backup/` forever** — kept as a rollback safety net, but nothing ever cleaned up an OLDER update's copy once a newer one succeeded, so disk usage grew by one full core-paths copy per update with no cap. Now pruned automatically the moment the next update's swap succeeds (keeping only the just-applied version's backup).
- **A zip-based (non-git) update never delivered the new `.htaccess` or `deploy/` deploy docs to a live install** — the swap only touches paths explicitly listed in `core_paths`, and both were missing from that list.
- **The System Info memory-usage widget understated any limit set in gigabytes** — `ini_get('memory_limit')` returns a shorthand string like `"5G"`; casting it straight to `(int)` silently drops the unit suffix, understating a gigabyte-scale limit by up to 1024x (e.g. `"5G"` read as 5 bytes-worth of MB instead of 5120).

### Changed
- **Added a `docker compose --profile nginx up` environment** for contributors — every other Docker profile runs PHP's built-in server, which never exercises nginx's own request routing (the `index.php`-only PHP-FPM carve-out, static asset caching, upload-size limits). This profile runs the real nginx + PHP-FPM stack locally against the exact files the live server uses (`deploy/nginx/oeparts.conf`, `deploy/php-fpm/oeparts-pool-overrides.conf`, bind-mounted, not copied), so nginx-specific regressions surface before they ship instead of after.

## 1.0.13 — 2026-07-25

### Added
- **Health Check, Failed Jobs, Cache Dashboard, System Updates, and Backup Management dashboards reworked** — all five now share a consistent dense-row/status-pill visual language instead of ad-hoc layouts. Health Check and Cache Dashboard gained history snapshots (`health:snapshot`, `cache:snapshot`, scheduled every 5 minutes) backing sparkline trends; Failed Jobs gained job-class grouping, exception classification, search, and bulk retry/flush; System Updates gained a pre-flight readiness strip, a visual step-by-step apply progress indicator, and a recent-updates strip; Backup Management gained a storage/last-backup/retention/encryption overview strip.
- **Release channel, auto-apply-security, and backup retention/schedule/stale-lock-threshold are now editable from the admin panel** instead of `.env`-only (System Updates → Update Settings, Backup Management → Backup Settings). Existing `.env` values seed as the initial database defaults.
- **A stuck shared backup/update lock is now visible and recoverable from the admin panel** — previously a crashed or abandoned run could hold the lock indefinitely with zero visibility anywhere in the UI, silently blocking every future backup and update until the hourly cleanup cron happened to catch it. Backup Management now shows the lock's owner and age live, with a "Release stale lock" action that only ever appears for a lock re-confirmed stale at click time — a genuinely in-progress run can never be interrupted this way.
- **System and Content admin navigation flattened from clusters into sidebar nav groups** — every sub-page kept its URL, but dashboard-style pages (tables, multi-column stat rows) are no longer cramped by a secondary cluster navigation panel eating horizontal space.

### Fixed
- **Login could silently fail on any install still served over plain HTTP** — `SESSION_SECURE_COOKIE` defaulted to `true` and the admin panel forced `https://` URL generation on any `APP_ENV=production` regardless of the site's actual scheme, so the browser refused to persist the session cookie on a host without SSL provisioned yet. Login now appeared to succeed but silently never logged you in. Both now follow the app's actual configured scheme.
- **The web installer's progress bar could stall right after creating the admin account** — the "already installed" guard could fire on the very next progress poll (that step legitimately populates the admins table three steps before the run finishes), redirecting the in-progress install away from itself.
- **`ActivityLog::create()` never actually persisted `created_at`** — not mass-assignable, and the model has no automatic timestamps, so every "last cleared" / "last warmed" / "last action" style display reading it (including the pre-existing Health Check page) always showed nothing. Fixed at the model level; can't silently regress per-call-site again.
- **The "Clear Application Cache" action was scanning the wrong Redis connection and prefix**, silently purging nothing on some installs.

## 1.0.12 — 2026-07-24

### Changed
- **Redis is now a recommended production upgrade, not a hard requirement** — removed the boot-time assertion that crashed `/install` on any host without the redis extension. Cache/session/queue now degrade cleanly to file/database/sync drivers when Redis isn't available, and `.env.example` defaults to `file`/`file`/`sync` so a fresh install always boots regardless of what the host offers. Redis is still recommended for production performance — just no longer required to get started.
- **The installer now runs as a chunked, resumable process** (`InstallManager`) instead of one long synchronous request, fixing a bug where a POST-only route made the final "install complete" step unreachable from a real browser session on some hosts.

### Added
- **HTTP-triggered cron fallback** — if a host's real system cron was never configured (or stopped running), scheduled tasks (backups, sitemap refresh, abandoned-cart emails, update checks) now still fire from a normal page load once the scheduler heartbeat goes stale, instead of silently never running. On by default; disable with `CRON_FALLBACK_ENABLED=false` if you have real cron configured and want to skip the (small, throttled) overhead entirely.
- **Fatal-error email notifications** — an uncaught exception inside the admin panel now emails every `super_admin` the moment it happens, instead of leaving a silent white screen with no signal anything broke.
- **Opt-in unattended security updates** — set `OE_UPDATE_AUTO_SECURITY=true` to have a daily scheduled command auto-apply (and auto-roll-back on failure) any release flagged security-only, using the same backup-first, pre-flight-gated update FSM as a manual "Apply Update" click. Off by default; routine feature releases always still require a manual click, and you're emailed the outcome either way.
- **Cross-server restore is now reachable via a dedicated CLI command** (`php artisan oeparts:backup:restore --import-manifest=...`) — see the README's "Moving to a new server" section for the full walkthrough.
- **Admin panel UI strings are now translatable** — labels across all Filament admin resources are wired through `lang/{locale}/admin.php` for the existing 5 languages, instead of being hardcoded English.

## 1.0.11 — 2026-07-21

### Fixed
- **Admin panel topbar logo didn't match the storefront navbar / login page brand mark** — the topbar version was a bare hexagon with a hardcoded, non-hoverable amber dot and a plain single-weight "OeParts" wordmark, missing the hover-rotate, hover color-invert, corner badge, and split-weight wordmark treatment used everywhere else the brand mark appears. Brought it in line with both reference implementations, verified in both light and dark sidebar themes.
- **5 more `canAccess()` calls could crash a real admin page with "Call to a member function hasRole() on null"** — confirmed live again, this time at the last step of an update (`ErrorMonitor::canAccess()`). The v1.0.9 sweep only grepped for the one-liner `auth('admin')->user()->hasRole(...)` shape and missed the equally common two-line variant used by `Filament\Clusters\System`, `ErrorMonitor`, `ServerMonitor`, `QueueMonitor`, and `HelpPage`. All 5 fixed the same way; the regression test no longer hand-lists classes to check — it now scans `app/Filament/` and calls every declared `canAccess()` as a guest automatically, so this class of bug can't quietly reappear via a hand-maintained list again.
- **"Update available" banner text was illegible in dark mode** — it paired a fixed, pale Filament shade variable as background with a semantic text-color token that flips to near-white in dark mode, so light mode looked fine while dark mode rendered the banner (and its "View details" button) essentially invisible. Replaced with explicit Tailwind `dark:` variants matching the pattern already used elsewhere in the codebase.
- **Storefront maintenance page depended on an external CDN Tailwind script with an incomplete local color config** — missing a class used in three places (timestamp, footer strip, copyright line), so those rendered with none of the intended styling; and depending on a CDN load is a poor fit for the one page guaranteed to render during infrastructure trouble anyway. Rewritten to use the same self-hosted `@vite` build as every other page, with a manual "Refresh page" button added alongside the existing passive copy.

### Performance
- **Full/Database backups felt slow on even a small site** — the resumable backup FSM advances one chunk per poll tick by design (shared-hosting safety), but "one chunk" meant exactly one database table-unit and one encrypted part per step regardless of how little work that actually was — tens of poll cycles (each with a floor delay) for a site with 60+ small tables. The database and encryption stages now batch as many whole units as fit within a wall-clock budget (5 seconds each, configurable) before yielding, bounded by time rather than a fixed count so slower hosts and larger tables/parts are still safe.

## 1.0.10 — 2026-07-19

### Fixed
- **The update-preview "I've reviewed the warnings" checkbox never enabled "Confirm & apply"** — the checkbox used a plain `wire:model` (no `.live`), which only syncs to the server on the next Livewire round-trip, while the Confirm button's disabled state is computed server-side from that same value. Checking the box updated the visible checkbox but never triggered a re-render, so on any release with a pre-flight WARNING (e.g. no release signature key configured, or the Recovery Console disarmed — both common on a fresh install with no extra setup) the button stayed permanently disabled with no way to proceed. Confirmed live via a real apply attempt. Changed to `wire:model.live` so checking the box immediately re-evaluates the button.

## 1.0.9 — 2026-07-19

### Fixed
- **A real 500 ("Call to a member function hasRole() on null") could hit any admin page** — 13 `canAccess()` checks across 3 navigation clusters (Settings, Reports, Content) and 10 pages (Cache Dashboard, Health Check, Failed Jobs, Log Viewer, Server Monitor's Scheduled Tasks page, Permission Matrix, Setup Assistant, Content Revisions, Inventory Log, Bulk Update Log) called `auth('admin')->user()->hasRole(...)`/`->hasAnyRole(...)` without a null-safe operator, unlike the rest of the codebase's established `auth('admin')->user()?->hasRole(...) ?? false` pattern. Whenever `auth('admin')->user()` is null while Filament resolves navigation (confirmed live: immediately after applying an in-app update, likely while the session hadn't fully rehydrated post framework-cache-rebuild), this crashed instead of just hiding the nav item. All 13 now use the null-safe pattern, and a new sweep test (`AdminCanAccessNullSafetyTest`) calls every one of them as a guest to make sure this can't quietly regress.

## 1.0.8 — 2026-07-19

### Fixed
- **A multi-step upgrade applied the latest release directly instead of the required intermediate hop** — when an install was more than one release behind and an intermediate release required stepping through in order (e.g. installed 1.0.5, latest 1.0.7, with 1.0.7 only installable from 1.0.6), clicking "Apply update now" always built its manifest from the *latest* release's own fields, ignoring the resolved upgrade path entirely. This applied the final release's zip directly, would have skipped any intermediate hop's migrations, and — since that manifest never carried the intermediate hop's own `min_version_to_update_from` — silently defeated the pre-flight version-compatibility check meant to block exactly this. Confirmed live on a real two-versions-behind install: pre-flight passed and the jump proceeded with no warning shown. `UpdateChecker` now resolves and exposes the correct next release to apply (the first hop in the upgrade path, not the latest), and the apply flow uses it.

## 1.0.7 — 2026-07-19

### Fixed
- **Full Backup could error out with no progress shown** — the file-backup stage backed up (and encrypted) the entire `vendor/` tree by design, which could crash/time out on large installs and gave zero percentage feedback while running. `vendor/` is now excluded from file backups by default (`composer install --no-dev` reproduces it on restore; set `OE_BACKUP_INCLUDE_VENDOR=true` to opt back into the old fully self-contained behaviour), `BackupManager::finalize()` is now wrapped so a late failure surfaces a real error instead of a bare crash, and every backup stage now reports a completion fraction so the dashboard shows a real progress bar and percentage instead of a bare spinner.
- **"Clear Cache" on the Health Check and Cache dashboards did a full `cache:clear` flush** — a full flush can take out sessions sharing the same Redis store, contrary to this project's own "never broad-flush the cache" rule. Both actions now do a targeted, key-scoped purge instead.

### Added
- **Backup type selection** — "Run backup now" now lets an admin choose Database Only, Files Only, or Full (database + files), instead of always running a full backup.
- **Update apply preview** — the "Apply update now" confirm step now shows the actual version jump, download size, migration count, breaking changes, ETA, and every pre-flight check (with any warnings requiring explicit acknowledgement) before an update starts, instead of a generic browser confirm dialog. The in-progress apply view also now shows a step counter and progress bar.
- **SEO: Sitemap regenerate button** — SEO & Meta settings now has a "Regenerate sitemap now" action and shows when the sitemap was last built, instead of only ever running via the daily scheduled job.
- **SEO: 404 monitor** — frontend 404s are now logged (deduplicated by path, with hit counts) and browsable from the admin, with a one-click "Create redirect" action that resolves the log entry.

### Changed
- **Settings pages save immediately** — replaced the "Save Actions" dropdown → "Preview Changes" → "Confirm Save" three-click flow with a single, direct Save button across every settings page. The change is still recorded in the settings activity log entries written to the main Activity Log; "Reset to Defaults" (a bulk overwrite) still asks for confirmation first.
- Removed the standalone "Settings Activity Log" page from the Settings grid (redundant with the main Activity Log).

## 1.0.6 — 2026-07-19

### Fixed
- **Reports → Sales/Search pages 500'd on every real MySQL install ("clicking Reports shows an error")** — confirmed live: `SalesTopProducts`, `SearchFailedQueries`, and `SearchTopSearches` (the "Top Selling Products" / "Failed Queries" / "Top Searches" report tables) all `GROUP BY` a set of columns while only `SELECT`ing an aggregated `MIN(id)`/`COALESCE(...)` alias — but Filament's `TableWidget` appends `ORDER BY {table}.id` for pagination-stable sorting unless told not to (`Table::hasDefaultKeySort()`, default enabled), and that appended clause references the *raw*, non-aggregated `id` column, which isn't functionally dependent on the `GROUP BY`. Under MySQL's default `sql_mode=only_full_group_by` this is a hard SQL syntax error (`SQLSTATE[42000]`), producing a 500 on every visit to the Sales Report page and the Search Intelligence report's two widgets — 100% reproducible on any standard MySQL/MariaDB install, every time. The test suite runs on SQLite (`.env.testing`), which does not enforce `ONLY_FULL_GROUP_BY`, so this shipped completely undetected by `php artisan test`; it only surfaced live, in a real browser against real MySQL. Added `->defaultKeySort(false)` to all three widgets (each already has its own explicit, correct `orderByDesc()`) and a new regression test that checks the actual mechanism (`hasDefaultKeySort()`) rather than relying on a specific database engine's strictness to catch it. `CustomersTop`, the fourth "Top" widget with the same shape, was verified safe as-is — its `GROUP BY` already includes the real `users.id`, so Filament's appended order-by was never invalid there.

## 1.0.5 — 2026-07-19

### Fixed
- **A stalled/corrupt download could permanently block every future update attempt with "HTTP 416"** — `UpdateDownloader::fetch()`'s resume logic only reset the on-disk file when it was *larger* than the expected size (`$from > $size`); a file that ended up sitting at *exactly* the expected size but with the wrong content (`isComplete()`'s sha256 check rejects it — e.g. from a byte-shifted resume, or a race between two concurrent apply attempts) fell through untouched, so every retry — and every future apply attempt, since the file persists on disk across FSM runs — requested `Range: bytes={size}-`, a range past end-of-file that any server correctly rejects with 416. Confirmed live: two separate real update attempts against GitHub's release CDN failed with the identical "Download failed after 4 attempt(s): Download server returned HTTP 416," with no way to recover short of manually finding and deleting the file. Changed the guard to `$from >= $size` so a full-size-but-invalid file is deleted and retried with a clean, no-Range request instead of looping the same invalid range forever.

## 1.0.4 — 2026-07-19

### Fixed
- **A backup abandoned mid-progress could block every future backup AND update indefinitely** — `BackupJanitor::cleanupPartials()` exists precisely to reclaim a run left `running` forever (e.g. an admin navigates away while "Run backup now" is still AJAX-polling, see v1.0.1) and release the shared lock it holds, but nothing ever called it: `BackupRetentionService` explicitly does not ("Failed/partial runs are the BackupJanitor's job, not retention's"), and `BackupDashboard`'s only use of `BackupJanitor` is the unrelated delete action's `purgeFiles()`. A stuck lock made `PreflightService`'s lock check fail for every subsequent update attempt too, surfacing as "Pre-flight failed: A backup or update is already in progress" with no way to resolve it from the UI. Added `oeparts:backup:cleanup-stale` (wraps `cleanupPartials()`) and scheduled it hourly, matching `config('backup.stale_after_seconds')`'s default 1-hour staleness threshold — a stuck lock now self-heals within the hour instead of requiring manual database/filesystem intervention.

## 1.0.3 — 2026-07-19

### Fixed
- **System Updates page looked download-only — the one-click "Apply update now" button was easy to miss** — the page header still read "One-click updates are coming soon — for now, follow the release notes," stale copy left over from before the one-click apply FSM (Chunk 3.5) shipped, even though `startApply()`/`pollApply()` have worked since. The prominent "Download release" button also rendered above the "Apply update now" section, reinforcing the impression that downloading was the only option. Fixed the header copy to describe the actual one-click flow, moved "Apply update now" to render first (the primary, most-visible action), and demoted "Download release" to an outlined secondary link labelled "Download release (manual install)" for admins without `apply updates` permission or who prefer a manual install.

## 1.0.2 — 2026-07-19

### Fixed
- **Settings pages had no way back except a small breadcrumb link** — every one of the 34 pages under the Settings cluster (34 concrete `SettingsPage` subclasses + the Activity Log table page) had only Save/Discard/Reset-to-Defaults actions in the form footer; the only path back to the Settings overview was a single-word cluster breadcrumb above the header, easy to miss. `SettingsPage::getHeaderActions()` now adds an explicit "Back to Settings" header action (linking to the cluster index) inherited by every subclass; the 3 pages that already had their own header actions for page-specific tools (`EmailSettings`' Send Test Email, `PaymentSettings`' Test Connection, `PerformanceSettings`' Test Cache) now merge it in via `...parent::getHeaderActions()` instead of overriding it away. `SettingsActivityLog` (which extends `Filament\Pages\Page` directly, not `SettingsPage`, since it's a table page) gets its own copy of the same action for consistency.

## 1.0.1 — 2026-07-19

### Fixed
- **Backup Management — "Run backup now" no longer hangs on "Running"** — the button dispatched `RunBackupJob`, which runs the entire backup (full profile, including `vendor/` by design) synchronously via `Artisan::call('oeparts:backup')`. Under `QUEUE_CONNECTION=sync` (the documented shared-hosting setup with no supervisor — README, CLAUDE.md rule #41) this ran the whole backup inline within the web request, blocking well past the web server's/PHP's timeout and leaving the run stuck at `running` with no progress, exactly as the Backup Engine's own chunked design (rule #48) was built to avoid. `BackupDashboard::runNow` now only calls `BackupManager::start()` (fast) and hands off to a new `pollBackup()` method, AJAX-polled one chunk per tick — the same pattern already used by the Update Engine's apply flow (`SystemUpdates::pollApply`). Also resumes polling automatically if the admin reloads the page mid-backup. `RunBackupJob`/`oeparts:backup` are unchanged and still correct for the scheduled cron backup, which has no web-request timeout to respect.

## 1.0.0 — 2026-07-19

Initial public release.

### Added
- Full Laravel 12 B2B/B2C e-commerce platform for genuine OEM auto parts — search-first, no product images, Industrial Blueprint design system
- 55 custom database tables + 8 framework tables
- OEM search engine with normalization, cross-references, and autocomplete
- 20 core modules (search, catalog, orders, payments, shipping, CMS, SEO, etc.)
- FilamentPHP 5.6.7 admin panel with 35+ resources, 4 clusters, 19 settings pages
- 5-language support (EN, DE, LT, FR, ES)
- 12 mail classes, 15 queue jobs, 25 email templates (HTML + plain text)
- Industrial Blueprint storefront design with 14 homepage sections
- Airwallex payment integration (card + bank transfer)
- Web installer wizard (6-step) + demo data seeder
- **OrderService** — centralized order lifecycle management with status transition validation, invoice number generation, and payment handling
- **ShippingService** — EU shipping zone detection, country-based method selection, free shipping threshold calculation, delivery estimation
- **HealthCheckService** — comprehensive system health checks (database, cache, queue, storage, scheduler, assets), surfaced via a `/health` endpoint and an admin dashboard
- **In-App Update & Recovery System** — a one-click, pure-PHP update mechanism built for shared hosting: automatic release detection with in-panel notifications, a 12-point safety pre-flight gate, chunked/resumable and mandatorily-encrypted (AES-256-GCM) backups with off-site (S3/SFTP) support and GFS retention, atomic file-swap updates with post-update verification and automatic rollback on failure, and a framework-independent Recovery Console for disaster recovery when the app itself can't boot
- Cryptographically signed releases (RSA-SHA256) with SHA-256 checksum verification
- GitHub Actions CI/CD: automated test suite (PHP 8.2/8.3/8.4 matrix) + tag-triggered release build/publish pipeline
- Branding system (colors/logo configurable from admin settings)
- CLAUDE.md — comprehensive AI coding rules documenting all critical patterns (bcmath, auth guards, cache rules, SEO, OTP, VIES)
- GitHub-quality documentation (README, SECURITY.md, CODE_OF_CONDUCT.md)

### Fixed
- JSON-LD structured data rendering under Laravel 12's new `@context` Blade directive (escaped to `@@context` across 9 storefront views)
- Installer, admin font, and legacy gradient-CSS cleanups
