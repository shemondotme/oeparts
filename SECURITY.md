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
