# Security Policy

OeParts handles customer PII, payment webhooks, and admin credentials for
real online stores. We take security reports seriously and appreciate
responsible disclosure.

## Supported Versions

Only the latest stable release receives security fixes. OeParts ships a
built-in [one-click update system](README.md#in-app-update--recovery-system)
specifically so self-hosted stores can stay current without CLI access —
please upgrade before reporting an issue that may already be fixed.

| Version | Supported |
|---|---|
| 1.0.x (latest) | ✅ |
| < 1.0 | ❌ |

## Reporting a Vulnerability

**Do not open a public GitHub issue for security vulnerabilities.** Public
issues are indexed and searchable — disclosing a live exploit path there
puts every self-hosted OeParts store at risk before a fix ships.

Instead, report privately through one of these channels:

1. **GitHub Security Advisories (preferred)** — open a
   [private security advisory](https://github.com/shemondotme/oeparts/security/advisories/new)
   for this repository. This lets us collaborate on a fix in a private fork
   before disclosure.
2. **Email** — [shemonbd247@gmail.com](mailto:shemonbd247@gmail.com) if you
   cannot use GitHub Advisories.

Please include:

- A description of the vulnerability and its impact (e.g. auth bypass,
  data exposure, payment manipulation, SQL injection)
- Steps to reproduce, or a proof-of-concept
- The affected version/commit
- Whether you believe it's already publicly known or exploited

### What to expect

- **Acknowledgement** within 72 hours
- **Initial assessment** (severity, affected versions) within 7 days
- **Fix or mitigation timeline** communicated once triaged — critical
  issues (auth bypass, payment/financial integrity, PII exposure) are
  prioritized above all other work
- **Credit** in the release notes and `CHANGELOG.md`, if you'd like it —
  tell us how you'd like to be attributed, or ask to stay anonymous

We do not currently run a paid bug bounty program.

## Scope

In scope:

- The OeParts application code in this repository (`app/`, `resources/`,
  `routes/`, `config/`, database migrations)
- The in-app Update & Recovery System, including `public/oe-recovery.php`
- Authentication/authorization logic for both guards (`web` and `admin`)
- Payment webhook handling (Airwallex HMAC verification, idempotency)
- The backup encryption pipeline (`OE_BACKUP_KEY`-derived AES-256-GCM)
- The site migration feature: `.oeparts` packages and their encryption, the one-time download links (`/_pkg/{token}`),
  fetching a package from a URL, the installer's import branch and the staging/quarantine safe modes
  (see [Site migration](#site-migration-oeparts-packages))

Out of scope:

- Vulnerabilities requiring physical access to a server, or a
  server/hosting environment already compromised by another means
  (e.g. a leaked `.env` or database credentials from a misconfigured host)
- Denial-of-service via raw traffic volume (rate-limiting misconfiguration
  in your own reverse proxy is not an OeParts bug)
- Issues in third-party dependencies — please report those upstream, but
  let us know too so we can track/patch on our side (`composer.lock` /
  `package-lock.json` pinning)
- Social engineering, phishing, or physical attacks against maintainers or
  users

## Hardening Notes for Self-Hosters

A few project-specific security properties worth knowing when you deploy:

- **Backups are mandatory-encrypted.** `OE_BACKUP_KEY` (not `APP_KEY`) must
  be set before any backup runs — see `.env.example`. Losing this key makes
  every existing backup unrecoverable; store it somewhere durable and
  separate from the database it protects.
- **The Recovery Console** (`public/oe-recovery.php`) is disabled by
  default and only activates during an armed update window, gated behind
  `OE_RECOVERY_KEY` (constant-time comparison), an optional IP allowlist,
  rate limiting, and single-use confirm tokens. Set `OE_RECOVERY_KEY` in
  production so it's available if you ever need it — an unset key means
  the console is fully disabled (`404`), not just unauthenticated.
- **Release signature verification** is opt-in via `OE_RELEASE_PUBLIC_KEY`
  — once set, the update pre-flight cryptographically verifies a release's
  authenticity (RSA-SHA256) in addition to its checksum, and blocks an
  unsigned or tampered release before it's ever applied.
- **All payment webhooks are HMAC-verified** before being trusted, and
  processed idempotently to prevent replay-driven double-fulfillment.

### File Ownership & Permissions

The self-updater (git-managed installs) runs `git checkout --force` and
`composer install` as whatever system user your web server (PHP-FPM) runs
as. That user must therefore **own every git-tracked file in the project**
— not just be able to read it. If any tracked file was ever created or
edited by a *different* user (most commonly: hand-editing a config file
with `sudo` directly on the server), git can no longer delete/overwrite it
during an update, and the update fails with `Permission denied` naming
that exact file.

This is why a WordPress install rarely runs into this: shared hosting
almost always gives PHP-FPM and SSH/SFTP access to the site under the
*same single account user* by construction, so there's structurally no
other user that could ever touch its files. A self-managed VPS with git
deployment has no such guarantee built in — you have to set it up
deliberately. The fix is one rule, not a list of exceptions:

> **Never edit a file inside the project directory as `root`/via bare
> `sudo`.** Either edit as the deploy user (`sudo -u deployuser -i`, then
> edit normally), or if a change genuinely requires root, `chown` the file
> back to the deploy user immediately afterward.

**One-time setup** (run once per server; replace `deployuser` with whatever
user PHP-FPM runs as — commonly `www-data`, sometimes a dedicated account):

```bash
# Make the deploy user own the entire project — code, configs, everything
# git tracks, PLUS the runtime-writable directories below.
sudo chown -R deployuser:deployuser /path/to/oeparts

# Laravel's own writable directories (cache, sessions, logs, compiled views).
find /path/to/oeparts/storage /path/to/oeparts/bootstrap/cache -type d -exec chmod 775 {} \;
find /path/to/oeparts/storage /path/to/oeparts/bootstrap/cache -type f -exec chmod 664 {} \;

# .env holds secrets — not git-tracked, but still needs the same owner;
# tighten it so only that user can read it at all.
chmod 600 /path/to/oeparts/.env
```

**What actually needs to be writable, and by whom** (everything else in
the tree only needs to be *readable* by the web server user):

| Path | Needs write access from | Why |
|---|---|---|
| `storage/app/*` | web server user | backup staging, uploaded media, generated invoices |
| `storage/framework/*` | web server user | compiled views, sessions/cache if file-driven |
| `storage/logs/*` | web server user | application logs |
| `storage/app/updates/*` | web server user | update lock file, recovery arm-flag, resumable state |
| `bootstrap/cache/*` | web server user | config/route/event cache |
| `public/storage` | — (symlink only) | points at `storage/app/public`; run `php artisan storage:link` once, never chmod the symlink target's permissions differently from `storage/app/public` itself |
| Every git-tracked file (`app/`, `config/`, `public/build/`, `deploy/`, `docker/`, …) | web server user (**own**, not just write) | `git checkout --force` during a self-update must be able to replace any of these |
| `.env` | web server user (own); `600` | secrets — no group/world access needed or wanted |
| `storage/app/migration/*`, `storage/app/import/`, `storage/app/instance/` | web server user | site migration: exported packages, the keys of a run in progress, import runs, the inbox, the mode ledger — see [Site migration](#site-migration-oeparts-packages) |

## Site migration (.oeparts packages)

Site migration packs a whole shop into one encrypted `.oeparts` file and unpacks it on another install (see
[the README](README.md#site-migration-oeparts)). A package is a **full copy of the shop**: customers and their
addresses, orders, payments, messages, the administrator accounts with their password hashes. This section says what
protects it, what does not, and what you are expected to do.

### Only import a package you trust completely

> **An imported package takes over the site.** Importing replaces the database, including the **administrator
> accounts and their password hashes**, the pages and sections and the **scripts and styles that are printed on every
> page** (the header and footer scripts and the custom CSS in the settings), the redirects and every other setting,
> and it writes uploaded files into the media folders. Whoever made the package decides who can sign in
> to your site afterwards and what runs in your visitors' browsers.
>
> **Encryption does not prove who made a package.** Anyone who knows its passphrase can make one that opens. Import
> only a package that you exported yourself, or that someone you trust completely handed to you over a channel you
> trust, and compare its fingerprint with the one the exporter saw (`php artisan oeparts:migrate:inspect` shows it once
> you give the passphrase).

Nothing can make an untrusted package safe to import. What the importer does limit is the *kind* of damage a hostile
package can do on its own: it carries rows of data, never SQL (they are written with bound parameters, only into the
tables of the groups you chose, and only when their columns are exactly the table's); a file inside it is refused if its path is absolute,
climbs out of its folder, is not under a folder of a group you chose, would be written through a symbolic link on
this server, or names something a web server or an interpreter would run (`.php`, `.phtml`, `.phar`, `.htaccess`,
`.user.ini`, `web.config`, keys and certificates, and so on, on any dot-separated part of the name; a file that
starts with a PHP tag is refused too); and compressed data is inflated only up to the length its authenticated
table of contents announces, so a small file cannot expand to gigabytes. Before the first change the importer also
makes a recovery point you can go back to.

### How a package is protected

- **Encryption.** Every block is encrypted and authenticated with AES-256-GCM, and each block is bound to its package,
  its entry, its position, the number of blocks and the length of the entry, so reordering, duplicating, dropping,
  truncating or splicing blocks is refused. There is no way to write an unencrypted package.
- **Key derivation.** The key comes from the passphrase through **Argon2id** (3 passes, 64 MiB) when the server has
  libsodium, and otherwise through **PBKDF2-HMAC-SHA512** with at least 1,000,000 iterations. Four sub-keys (the
  check, the data, the secrets and the fingerprint) are derived from it with HKDF-SHA256, salted with the hash of the
  whole header, so changing any header byte, for example to weaken the key derivation, changes every key and the
  passphrase check fails. The parameters are read from the file, so they are checked against hard bounds *before*
  any derivation runs: a hostile file cannot ask the server for gigabytes of memory or billions of iterations.
- **A passphrase you can trust.** With Argon2id the passphrase must be at least 14 characters. PBKDF2 is cheap to
  attack on graphics cards, so under PBKDF2 only the **generated** passphrase (24 random characters, 120 bits,
  `--generate-passphrase` or "Make one for me") or one of at least 20 characters that also scores 80 bits on a
  deliberately pessimistic estimate is accepted. The same rule applies in the admin panel, on the command line and
  in the installer.
- **Wrong guesses.** After 5 wrong passphrases for one package from one address the next attempts are refused, before
  any key derivation, for 60 seconds, doubling with every further failure up to 15 minutes. (The cost of the
  derivation is the real brake; this stops hammering and tells a person to wait.) The admin password that guards
  downloading, sharing, deleting and importing a package is limited too: 5 wrong passwords in 10 minutes, per admin
  and address.

### Passphrase custody

The passphrase is the **only** thing that protects a package in transit, and **it cannot be recovered**: not by us,
not from the server. It is never stored, in a session, a cookie, a state file, the database, a URL or a log.

- It is read only from a hidden prompt, from `--passphrase-file=`, or from the environment variables
  `OEPARTS_PASSPHRASE` / `OE_MIGRATE_PASSPHRASE`. `--passphrase=VALUE` is refused, because a command-line argument
  is visible to every user of the machine and stays in the shell history.
- A run that takes several requests (a browser polling, a queue job, a cron nudge) must not ask again each time, so
  the keys *derived* from the passphrase (not the passphrase) are kept for the length of the run in
  `storage/app/migration/escrow/`, wrapped with AES-256-GCM under a key derived from `APP_KEY`, bound to the run, in
  a file readable by the web user only. The file is deleted when the run ends, and the janitor removes any left
  behind after `OE_MIGRATION_ESCROW_TTL` seconds (24 hours by default). A copy of that file alone is useless without
  `APP_KEY`; someone who can read both already controls the site.
- Send the passphrase by a **different route** than the file or the link, and do not put it in a ticket, a chat that
  keeps history, or the same email as the link.

### Secrets are left out by default

Saved credentials (payment-gateway keys, the SMTP password, API keys, push keys) stay on the old server unless the
person exporting asks for them (`--include-secrets`, or the switch in the admin panel). Then they travel locked under
a separate sub-key *inside* the already encrypted package, are opened only when the importer asks for them, and are
re-encrypted with the **new** site's `APP_KEY`. A secret the new site cannot read is emptied and listed in the report,
never left looking configured. A `staging` package, whose personal data is scrambled, can never carry secrets.
`.env` never travels: `APP_KEY`, the database credentials and `OE_BACKUP_KEY` stay per server.

### One-time download links (`/_pkg/{token}`)

An exported package can be handed to the new server through a link made with `php artisan oeparts:migrate:share` or
the admin panel (the admin panel asks for the password again). The link gives **access control and protection against
guessing, not secrecy**: the file behind it is encrypted with the passphrase.

- The token is 32 random bytes; only its SHA-256 is stored, never the token. A link lives 24 hours by default (7 days
  at most), at most 5 links are alive at once, and it can be limited to one IP address and revoked.
- The first request **claims** the link and receives a claim secret in the `X-OeParts-Claim` header; only that
  downloader can resume (for 12 hours after the first request, even past the link's own expiry), and the link is
  *consumed* when the whole file has been sent. Two simultaneous first requests: exactly one wins.
- **Every refusal is the same plain `404`**: an unknown, expired, used, revoked or wrongly claimed link, or one limited
  to another address. The real reason is only in the audit log, so the endpoint cannot be used to find out which tokens
  exist. After 20 refusals from one address in an hour it answers `429`. `HEAD` is refused so that only a real download
  can claim a link.
- The file name comes only from the database row, the path must resolve inside the exports folder and must not be a
  link, and the size must equal what was recorded. The share string's `#sha256=…` part never reaches the server; it
  lets the importer check the file it received.
- Downloads are limited to one at a time by default (`OE_SHARE_MAX_STREAMS`), so long downloads cannot starve the
  PHP workers; a second request gets `503` with `Retry-After`.
- IP addresses are taken from the request. Behind a reverse proxy this is only right when `TRUSTED_PROXIES` is set in
  `.env`; without it the claim secret is the real protection and the address limit is best effort.
- The route works while the shop is frozen for an export, and it creates no session or cookie.

### Fetching a package from a URL (SSRF)

Telling a server to fetch a URL is how servers get tricked into reaching things they should not (internal services,
cloud metadata endpoints). Fetching a package from a link, and fetching a product image from a URL in the admin panel,
go through **one** policy and one HTTP client; nothing else in the feature reaches the internet.

- Only `https`, only port 443 unless the owner lists more in `OE_MIGRATION_ALLOWED_PORTS`, no user name or password in
  the address, no numeric host written in an unusual form (`2130706433`, `0x7f.1`, `127.1`), and names like
  `localhost` or ending in `.local`, `.internal` and similar are refused. (The product-image downloader also accepts
  plain `http` on port 80, for a small public picture; a package transfer never does.)
- The name is resolved **once**, and **every** address it resolves to (IPv4 and IPv6) must be a public internet
  address: loopback, private ranges, carrier-grade NAT, link-local (which includes cloud metadata at `169.254.169.254`),
  `168.63.129.16`, unique-local IPv6, multicast and reserved ranges are all refused, including IPv4 hidden inside IPv6.
  The validated address is then **pinned** for the connection (`CURLOPT_RESOLVE`), so the name cannot point somewhere
  else between the check and the connection (DNS rebinding); the certificate is still checked against the host name.
- At most 3 redirects, each one validated and pinned again, never to `http` or to another port; no proxy; no content
  encoding; the body is accepted only when the headers prove it is the range that was asked for; the first bytes must
  be the package's magic, so a login page behind a redirect fails at once; a size cap (`OE_MIGRATION_FETCH_MAX_BYTES`,
  32 GiB) and timeouts apply.
- This protection **cannot be switched off from the admin panel or the settings.** For an intranet or a rehearsal where
  both sites live on private addresses there is one switch, `OE_MIGRATION_ALLOW_PRIVATE_HOSTS=true`, and it is read from
  `.env` only. `OE_MIGRATION_ALLOWED_HOSTS` can restrict downloads to a list of hosts.

### The installer's import branch

The web installer has no login, so its import branch is built to give a stranger nothing.

- It only ever takes a package from the server's own `storage/app/import/` folder (put there with SFTP). **The
  installer has no upload route**, and a package is never executed or decrypted until a passphrase unlocks it.
- The branch belongs to **one browser**: the first request that starts it sets a random 256-bit nonce in an HttpOnly,
  `SameSite=Strict` cookie scoped to `/install` (and `Secure` over https); only its hash is stored. Any other browser
  sees a plain "Someone else started this install" page with no data and no way to change anything. It can be taken
  over after 30 minutes without activity, or on the server with `php artisan oeparts:migrate:wizard-reset`. Starting an
  import is a `POST` with the installer's CSRF token.
- **Passphrases are not accepted over plain `http`** (unless the host is the machine itself): anyone on the way could
  read it. Use `https` or the command line. `OE_INSTALLER_ALLOW_HTTP_PASSPHRASE=true` lifts this; it is read from
  `.env` only, never a form field, and you take the risk. `OE_INSTALLER_LIST_INBOX=false` stops the installer showing
  the file names in the inbox, for a host where the names themselves are private.
- The installer can only replace a site **nobody has used**. A site with orders, customers, payments, invoices,
  messages, subscribers or refund requests, or more rows than the recovery point can hold, is refused: use the admin
  panel or the command line, which ask for `REPLACE` typed out (and `--replace-populated` for a used site).
- Importing through the admin panel needs the `import migration` permission (only `super_admin` has it unless you grant
  it by hand); the command line needs a shell on the server. A site is replaced only after `REPLACE` is typed out
  (and, in the admin panel, the admin's password again); no flag skips the recovery point.

### Staging copies and quarantine

A site that was moved in arrives in **quarantine**, and a test copy then lives in **staging**; the real shop is
**production**. The mode is only in `.env` (`OE_MODE`); a value that is not one of the three is treated as quarantine
and logged as critical, so a typo can never turn a copy back into the real shop.

- **Mail** is dropped. A staging copy may still mail an allow-list of addresses or `@domains` (`OE_MAIL_ALLOWLIST`),
  with `[STAGING]` in the subject; quarantine mails nobody.
- **Money**: the card gateway is forced to its sandbox, Paysera (which has no sandbox) is refused, and a payment copied
  from the real shop can never be captured or released from the copy.
- **Search engines**: `robots.txt` disallows everything and every page is `noindex`.
- **Everything that reaches outwards** reads as switched off (tracking ids, search-engine pings, Cloudflare, push,
  scheduled backups, automatic cleaning, automatic updates); the stored values are never edited.
- **Scheduled work** that reaches customers does not run; a copy with no real cron does not run the scheduler through
  the web cron fallback at all unless `OE_NONPROD_HTTP_CRON=true`.
- **Numbers**: order and invoice numbers start with `T-`, so a test number is never mistaken for a real one.
- A change of mode restarts the queue workers first (a worker reads `.env` once), and the launch checks prove that a
  worker really follows the mode; that one check can never be waived.

Staging is **not** a security boundary for the data: a `staging` package has names, emails, phone numbers, addresses,
IP addresses and free text scrambled or blanked, but a short list of columns is kept as it is on purpose (the
administrator accounts, order and payment references, the shop's own settings and similar; the list is in the README
and in the export wizard). Treat a staging copy as the real shop's data until you have read that list. Going live is refused while
the copy holds anything created on it since it was made (test customers, test orders, test payments) unless that is
explicitly discarded first, so test data is not carried into the real shop by accident.

### What is recorded

Every export, link, download, import and rollback is written to the `migration` log channel and to
`storage/app/migration/history.ndjson`, a file that survives the database swap an import makes (an import writes its
audit line *before* it changes anything and refuses to start if it cannot). Keys that look like tokens, passphrases,
passwords, secrets, claims or keys are dropped from what is written, and URLs are cut down to their host.
